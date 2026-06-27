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

$http = new HttpServer(function (ServerRequestInterface $request) use ($contentService, $authorService, $ratingService, $cacheManager, $cacheOptions) {
    $path = $request->getUri()->getPath();

    $headers = [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
    ];

    if ($request->getMethod() === 'OPTIONS') {
        return new Response(200, $headers);
    }

    return $cacheManager->wrap(
        'api_' . md5($path),
        function () use ($path, $request, $contentService, $authorService, $ratingService) {
            return \React\Promise\resolve(null)->then(function () use ($path, $request, $contentService, $authorService, $ratingService) {
                if ($path === '/api/feed') {
                    $feed = $ratingService->getTrendingContent(20);
                    return json_encode(['success' => true, 'data' => $feed]);
                }

                if ($path === '/api/projects/random') {
                    $content = $contentService->getRandomContent();
                    return json_encode(['success' => true, 'data' => $content]);
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
                    
                    return json_encode(['success' => true]);
                }

                return json_encode(['success' => false, 'error' => 'Endpoint Not Found']);
            });
        },
        $cacheOptions
    )->then(function ($data) use ($headers) {
        $statusCode = str_contains($data, '"error":"Endpoint Not Found"') ? 404 : 200;
        return new Response($statusCode, $headers, $data);
    })->catch(function (\Throwable $e) use ($headers) {
        return new Response(500, $headers, json_encode(['success' => false, 'error' => $e->getMessage()]));
    });
});

$socket = new SocketServer('0.0.0.0:8080');
$http->listen($socket);

echo "API Server with AsyncCache running at http://0.0.0.0:8080\n";
