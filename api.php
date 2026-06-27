<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use Fyennyi\AsyncCache\AsyncCacheManager;
use Fyennyi\AsyncCache\CacheOptions;
use React\Cache\ArrayCache;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$botToken = $_ENV['BOT_TOKEN'] ?? 'dummy';
$bot = new \morfeditorial\MyBot($botToken);
$container = $bot->getContainer();
$contentService = $container->get('content_service');
$authorService = $container->get('author_service');
$ratingService = $container->get('rating_service');

$cache = new ArrayCache();
$cacheManager = new AsyncCacheManager(
    AsyncCacheManager::configure($cache)->build()
);

$cacheOptions = new CacheOptions(ttl: 30);

$sseConnections = new \SplObjectStorage();

$http = new HttpServer(function (ServerRequestInterface $request) use ($contentService, $authorService, $ratingService, $cacheManager, $cacheOptions, $sseConnections) {
    $path = $request->getUri()->getPath();

    $headers = [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
    ];

    if ($request->getMethod() === 'OPTIONS') {
        return new Response(200, $headers);
    }

    if ($path === '/api/stream') {
        $stream = new \React\Stream\ThroughStream();
        $sseConnections->offsetSet($stream);
        $stream->on('close', function () use ($sseConnections, $stream) {
            $sseConnections->offsetUnset($stream);
        });

        return new Response(200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*'
        ], $stream);
    }

    $handler = function () use ($path, $request, $contentService, $authorService, $ratingService, $sseConnections) {
        return \React\Promise\resolve(null)->then(function () use ($path, $request, $contentService, $authorService, $ratingService, $sseConnections) {
            if ($path === '/api/feed') {
                $feed = $ratingService->getTrendingContent(20);
                return json_encode(['success' => true, 'data' => $feed]);
            }

            if ($path === '/api/projects/random') {
                $content = $contentService->getRandomContent();
                return json_encode(['success' => true, 'data' => $content]);
            }
            
            if (preg_match('#^/api/projects/(\d+)$#', $path, $matches) && $request->getMethod() === 'GET') {
                $projectId = (int) $matches[1];
                $project = $contentService->getProjectById($projectId);
                if (!$project) return json_encode(['success' => false, 'error' => 'Project not found']);
                
                $stats = $ratingService->getContentStats($projectId);
                $project['likes_count'] = $stats['likes'];
                $project['dislikes_count'] = $stats['dislikes'];
                $project['views_count'] = $stats['views'];
                
                return json_encode(['success' => true, 'data' => $project]);
            }
            
            if (preg_match('#^/api/projects/(\d+)/comments$#', $path, $matches) && $request->getMethod() === 'GET') {
                $projectId = (int) $matches[1];
                $comments = $contentService->getComments($projectId);
                return json_encode(['success' => true, 'data' => $comments]);
            }
            
            if (preg_match('#^/api/projects/(\d+)/comments$#', $path, $matches) && $request->getMethod() === 'POST') {
                $projectId = (int) $matches[1];
                $body = json_decode((string) $request->getBody(), true);
                if (!$body || !isset($body['text'], $body['user_id'])) {
                    return json_encode(['success' => false, 'error' => 'Missing data']);
                }
                
                $authorName = $body['author_name'] ?? 'Користувач';
                $commentId = $contentService->addComment($projectId, $body['user_id'], $authorName, $body['text']);
                
                $newComment = [
                    'id' => $commentId,
                    'content_id' => $projectId,
                    'user_id' => $body['user_id'],
                    'author_name' => $authorName,
                    'text' => $body['text'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // SSE Broadcast
                $payload = json_encode([
                    'type' => 'NEW_COMMENT',
                    'content_id' => $projectId,
                    'comment' => $newComment
                ]);
                foreach ($sseConnections as $conn) {
                    $conn->write("data: {$payload}\n\n");
                }
                
                return json_encode(['success' => true, 'data' => $newComment]);
            }
            
            if ($path === '/api/authors/top') {
                $authors = $authorService->getTopAuthors();
                return json_encode(['success' => true, 'data' => $authors]);
            }

            if (preg_match('#^/api/projects/search/(.+)$#', $path, $matches)) {
                $query = urldecode($matches[1]);
                $results = $contentService->searchContent($query);
                return json_encode(['success' => true, 'data' => $results]);
            }
            
            if (preg_match('#^/api/authors/(\d+)$#', $path, $matches)) {
                $authorId = (int) $matches[1];
                $author = $authorService->getAuthorById($authorId);
                
                if (!$author || $author['state'] !== 'public') {
                    return json_encode(['success' => false, 'error' => 'Author not found']);
                }
                
                return json_encode(['success' => true, 'data' => $author]);
            }
            
            if (preg_match('#^/api/authors/(\d+)/projects$#', $path, $matches)) {
                $authorId = (int) $matches[1];
                $author = $authorService->getAuthorById($authorId);
                
                if (!$author || $author['state'] !== 'public') {
                    return json_encode(['success' => false, 'error' => 'Author not found']);
                }
                
                $queryParams = $request->getQueryParams();
                $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 10;
                $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : 0;
                
                $projects = $authorService->getAuthorProjects($authorId, $limit, $offset);
                return json_encode(['success' => true, 'data' => $projects]);
            }
            
            if ($path === '/api/categories') {
                $categories = $contentService->getAllCategories();
                return json_encode(['success' => true, 'data' => $categories]);
            }
            
            if (preg_match('#^/api/categories/(\d+)$#', $path, $matches)) {
                $categoryId = (int) $matches[1];
                $category = $contentService->getCategoryById($categoryId);
                
                if (!$category) {
                    return json_encode(['success' => false, 'error' => 'Category not found']);
                }
                
                return json_encode(['success' => true, 'data' => $category]);
            }
            
            if (preg_match('#^/api/categories/(\d+)/projects$#', $path, $matches)) {
                $categoryId = (int) $matches[1];
                $category = $contentService->getCategoryById($categoryId);
                
                if (!$category) {
                    return json_encode(['success' => false, 'error' => 'Category not found']);
                }
                
                $queryParams = $request->getQueryParams();
                $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 10;
                $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : 0;
                
                $projects = $contentService->getCategoryProjects($categoryId, $limit, $offset);
                return json_encode(['success' => true, 'data' => $projects]);
            }
            
            if (preg_match('#^/api/user/(\d+)/interactions$#', $path, $matches)) {
                $userId = (int) $matches[1];
                $interactions = $ratingService->getUserInteractions($userId);
                return json_encode(['success' => true, 'data' => $interactions]);
            }
            
            if ($path === '/api/interact' && $request->getMethod() === 'POST') {
                $body = (string) $request->getBody();
                $data = json_decode($body, true);
                
                if (!isset($data['content_id'], $data['type'])) {
                    return json_encode(['success' => false, 'error' => 'Missing content_id or type']);
                }
                
                $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;
                $contentId = (int) $data['content_id'];
                $type = (string) $data['type'];
                
                if ($type === 'view') {
                    $ratingService->addView($contentId, $userId);
                } else {
                    if (!$userId) {
                        return json_encode(['success' => false, 'error' => 'User ID is required for liking/disliking']);
                    }
                    $ratingService->interact($userId, $contentId, $type);
                }
                
                // Broadcast new stats via SSE
                $stats = $ratingService->getContentStats($contentId);
                $payload = json_encode([
                    'type' => 'STATS_UPDATE',
                    'content_id' => $contentId,
                    'likes' => $stats['likes'],
                    'dislikes' => $stats['dislikes'],
                    'views' => $stats['views']
                ]);
                
                foreach ($sseConnections as $conn) {
                    $conn->write("data: {$payload}\n\n");
                }
                
                return json_encode(['success' => true]);
            }

            return json_encode(['success' => false, 'error' => 'Endpoint Not Found']);
        });
    };

    if ($request->getMethod() === 'POST' || str_starts_with($path, '/api/user/')) {
        $promise = $handler();
    } else {
        $promise = $cacheManager->wrap('api_' . md5($path), $handler, $cacheOptions);
    }

    return $promise->then(function ($data) use ($headers) {
        $statusCode = str_contains($data, '"error":"Endpoint Not Found"') ? 404 : 200;
        return new Response($statusCode, $headers, $data);
    })->catch(function (\Throwable $e) use ($headers) {
        return new Response(500, $headers, json_encode(['success' => false, 'error' => $e->getMessage()]));
    });
});

$socket = new SocketServer('0.0.0.0:8080');
$http->listen($socket);

echo "API Server with AsyncCache running at http://0.0.0.0:8080\n";
