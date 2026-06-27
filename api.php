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
$roleService = $container->get('role_service');
$notificationService = $container->get('notification_service');

$cache = new ArrayCache();
$cacheManager = new AsyncCacheManager(
    AsyncCacheManager::configure($cache)->build()
);

$cacheOptions = new CacheOptions(ttl: 30);

$sseConnections = new \SplObjectStorage();

$http = new HttpServer(function (ServerRequestInterface $request) use ($contentService, $authorService, $ratingService, $roleService, $notificationService, $cacheManager, $cacheOptions, $sseConnections, $botToken) {
    $path = $request->getUri()->getPath();

    $headers = [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
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

    $handler = function () use ($path, $request, $contentService, $authorService, $ratingService, $roleService, $notificationService, $sseConnections, $botToken) {
        static $avatarCache = [];
        return \React\Promise\resolve(null)->then(function () use ($path, $request, $contentService, $authorService, $ratingService, $roleService, $notificationService, $sseConnections, &$avatarCache, $botToken) {
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
                
                // Fetch avatars from Telegram API
                global $botToken;
                
                foreach ($comments as &$comment) {
                    $uId = $comment['user_id'];
                    if (!isset($avatarCache[$uId])) {
                        $avatarCache[$uId] = null;
                        try {
                            $postData = json_encode(['user_id' => $uId, 'limit' => 1]);
                            $opts = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $postData]];
                            $context = stream_context_create($opts);
                            $photosJson = @file_get_contents("https://api.telegram.org/bot{$botToken}/getUserProfilePhotos", false, $context);
                            
                            if ($photosJson) {
                                $photos = json_decode($photosJson, true);
                                if (!empty($photos['result']['photos'][0][0]['file_id'])) {
                                    $fileId = $photos['result']['photos'][0][0]['file_id'];
                                    
                                    $filePostData = json_encode(['file_id' => $fileId]);
                                    $fileOpts = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $filePostData]];
                                    $fileContext = stream_context_create($fileOpts);
                                    
                                    $fileJson = @file_get_contents("https://api.telegram.org/bot{$botToken}/getFile", false, $fileContext);
                                    if ($fileJson) {
                                        $fileData = json_decode($fileJson, true);
                                        if (!empty($fileData['result']['file_path'])) {
                                            $avatarCache[$uId] = "https://api.telegram.org/file/bot{$botToken}/" . $fileData['result']['file_path'];
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {}
                    }
                    $comment['author_avatar'] = $avatarCache[$uId];
                }
                unset($comment);
                
                return json_encode(['success' => true, 'data' => $comments]);
            }
            
            if (preg_match('#^/api/projects/(\d+)/comments$#', $path, $matches) && $request->getMethod() === 'POST') {
                $projectId = (int) $matches[1];
                $body = json_decode((string) $request->getBody(), true);
                if (!$body || !isset($body['text'], $body['user_id'])) {
                    return json_encode(['success' => false, 'error' => 'Missing data']);
                }
                
                $authorName = $body['author_name'] ?? 'Користувач';
                $parentId = isset($body['parent_id']) ? (int) $body['parent_id'] : null;
                
                $commentId = $contentService->addComment($projectId, $body['user_id'], $authorName, $body['text'], $parentId);
                
                global $botToken;
                $authorAvatar = $avatarCache[$body['user_id']] ?? null;
                
                $newComment = [
                    'id' => $commentId,
                    'content_id' => $projectId,
                    'user_id' => $body['user_id'],
                    'author_name' => $authorName,
                    'author_avatar' => $authorAvatar,
                    'text' => $body['text'],
                    'parent_id' => $parentId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => null
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
                
                // Notify Project Author and/or Parent Comment Author
                try {
                    $project = $contentService->getContentById($projectId);
                    $notifiedUsers = []; // Track who we notified to avoid double notifications
                    
                    // 1. Notify Parent Comment Author (if it's a reply)
                    if ($parentId) {
                        $parentComment = $contentService->getCommentById($parentId);
                        if ($parentComment && !empty($parentComment['user_id'])) {
                            $targetUserId = (int) $parentComment['user_id'];
                            if ($targetUserId !== (int) $body['user_id']) {
                                $shortText = mb_substr($body['text'], 0, 50) . (mb_strlen($body['text']) > 50 ? '...' : '');
                                $notif = $notificationService->notify($targetUserId, 'new_comment', $projectId, "Відповідь на ваш коментар: {$shortText}");
                                $notifiedUsers[] = $targetUserId;
                                
                                $notifPayload = json_encode(['type' => 'NEW_NOTIFICATION', 'notification' => $notif]);
                                foreach ($sseConnections as $conn) {
                                    $conn->write("data: {$notifPayload}\n\n");
                                }
                            }
                        }
                    }

                    // 2. Notify Project Author
                    if ($project && !empty($project['author_id'])) {
                        $projectAuthor = $authorService->getAuthorById((int) $project['author_id']);
                        if ($projectAuthor && !empty($projectAuthor['telegram_user_id'])) {
                            $targetUserId = (int) $projectAuthor['telegram_user_id'];
                            // Don't notify the project author if they are the one commenting, OR if we already notified them because they were the parent comment author
                            if ($targetUserId !== (int) $body['user_id'] && !in_array($targetUserId, $notifiedUsers, true)) {
                                $shortText = mb_substr($body['text'], 0, 50) . (mb_strlen($body['text']) > 50 ? '...' : '');
                                $notif = $notificationService->notify($targetUserId, 'new_comment', $projectId, "Новий коментар до вашого проєкту: {$shortText}");
                                
                                $notifPayload = json_encode(['type' => 'NEW_NOTIFICATION', 'notification' => $notif]);
                                foreach ($sseConnections as $conn) {
                                    $conn->write("data: {$notifPayload}\n\n");
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {}

                return json_encode(['success' => true, 'data' => $newComment]);
            }
            
            if (preg_match('#^/api/user/(\d+)/notifications$#', $path, $matches) && $request->getMethod() === 'GET') {
                $userId = (int) $matches[1];
                $notifications = $notificationService->getUserNotifications($userId);
                $unreadCount = $notificationService->getUnreadCount($userId);
                return json_encode(['success' => true, 'data' => ['notifications' => $notifications, 'unread_count' => $unreadCount]]);
            }
            
            if (preg_match('#^/api/user/(\d+)/notifications/read-all$#', $path, $matches) && $request->getMethod() === 'PUT') {
                $userId = (int) $matches[1];
                $notificationService->markAllAsRead($userId);
                return json_encode(['success' => true]);
            }
            
            if (preg_match('#^/api/notifications/(\d+)/read$#', $path, $matches) && $request->getMethod() === 'PUT') {
                $notifId = (int) $matches[1];
                $body = json_decode((string) $request->getBody(), true);
                $userId = $body['user_id'] ?? 0;
                $notificationService->markAsRead($notifId, $userId);
                return json_encode(['success' => true]);
            }

            if (preg_match('#^/api/comments/(\d+)$#', $path, $matches) && $request->getMethod() === 'PUT') {
                $commentId = (int) $matches[1];
                $body = json_decode((string) $request->getBody(), true);
                if (!$body || !isset($body['text'], $body['user_id'])) {
                    return json_encode(['success' => false, 'error' => 'Missing data']);
                }
                
                $success = $contentService->editComment($commentId, $body['user_id'], $body['text']);
                if ($success) {
                    // SSE Broadcast
                    $payload = json_encode([
                        'type' => 'EDIT_COMMENT',
                        'comment_id' => $commentId,
                        'text' => $body['text'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    foreach ($sseConnections as $conn) {
                        $conn->write("data: {$payload}\n\n");
                    }
                    return json_encode(['success' => true]);
                }
                
                return json_encode(['success' => false, 'error' => 'Failed to edit or unauthorized']);
            }
            
            if (preg_match('#^/api/comments/(\d+)$#', $path, $matches) && $request->getMethod() === 'DELETE') {
                $commentId = (int) $matches[1];
                $body = json_decode((string) $request->getBody(), true);
                $userId = $body['user_id'] ?? 0;
                
                $comment = $contentService->getCommentById($commentId);
                if (!$comment) {
                    return json_encode(['success' => false, 'error' => 'Not found']);
                }
                
                $isOwner = ((int) $comment['user_id'] === (int) $userId);
                
                $isModerator = false;
                $roles = $roleService->getUserRoleNames($userId);
                foreach ($roles as $r) {
                    if ($roleService->doesRoleInclude($r, 'moderator')) {
                        $isModerator = true;
                        break;
                    }
                }
                
                if ($isOwner || $isModerator) {
                    $contentService->deleteCommentItem($commentId);
                    $payload = json_encode([
                        'type' => 'DELETE_COMMENT',
                        'comment_id' => $commentId
                    ]);
                    foreach ($sseConnections as $conn) {
                        $conn->write("data: {$payload}\n\n");
                    }
                    return json_encode(['success' => true]);
                }
                
                return json_encode(['success' => false, 'error' => 'Unauthorized']);
            }
            
            if (preg_match('#^/api/user/(\d+)/roles$#', $path, $matches) && $request->getMethod() === 'GET') {
                $userId = (int) $matches[1];
                $roles = $roleService->getUserRoleNames($userId);
                $isModerator = false;
                foreach ($roles as $r) {
                    if ($roleService->doesRoleInclude($r, 'moderator')) {
                        $isModerator = true;
                        break;
                    }
                }
                return json_encode(['success' => true, 'data' => ['roles' => $roles, 'is_moderator' => $isModerator]]);
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
