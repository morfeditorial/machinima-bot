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

$cache = new ArrayCache();
$cacheManager = new AsyncCacheManager(
    AsyncCacheManager::configure($cache)->build()
);

$cacheOptions = new CacheOptions(ttl: 30);

$http = new HttpServer(function (ServerRequestInterface $request) use ($contentService, $authorService, $cacheManager, $cacheOptions) {
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
        function () use ($path, $contentService, $authorService) {
            return \React\Promise\resolve(null)->then(function () use ($path, $contentService, $authorService) {
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
