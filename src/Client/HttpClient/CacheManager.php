<?php

declare(strict_types=1);

namespace App\Client\HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CacheManager
{
    private Client $client;

    public function __construct(string $cacheFolder)
    {
        $stack = HandlerStack::create();
        $stack->push(
            new CacheMiddleware(
                new PrivateCacheStrategy(
                    new Psr6CacheStorage(
                        new FilesystemAdapter('WebToElasticms', 0, $cacheFolder.DIRECTORY_SEPARATOR.'cache')
                    )
                )
            ),
            'cache'
        );
        $stack->push(new CacheMiddleware(), 'cache');
        $this->client = new Client(['handler' => $stack]);
    }

    public function get(string $url): HttpResult
    {
        return new HttpResult($this->client->get($url));
    }

    public function head(string $url): HttpResult
    {
        return new HttpResult($this->client->head($url));
    }
}
