<?php

declare(strict_types=1);

namespace App\Client\HttpClient;

use App\Client\WebToElasticms\Helper\Url;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CacheManager
{
    private Client $client;
    /** @var UrlReport[] */
    private array $cachedReport = [];
    private string $cacheFolder;

    public function __construct(string $cacheFolder)
    {
        $this->cacheFolder = $cacheFolder;
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
        try {
            return new HttpResult($this->client->get($url));
        } catch (ClientException|RequestException $e) {
            return new HttpResult($e->getResponse(), $e->getMessage());
        }
    }

    public function head(string $url): HttpResult
    {
        $head = new HttpResult($this->client->head($url));
        if ($head->hasResponse() && 405 === $head->getResponse()->getStatusCode()) {
            return $this->get($url);
        }

        return $head;
    }

    public function testUrl(Url $url): UrlReport
    {
        if (isset($this->cachedReport[$url->getUrl()])) {
            return $this->cachedReport[$url->getUrl()];
        }
        $report = $this->generateUrlReport($url);
        $this->cachedReport[$url->getUrl()] = $report;

        return $report;
    }

    private function generateUrlReport(Url $url): UrlReport
    {
        if (!$url->isCrawlable()) {
            return new UrlReport($url, 0, 'Not crawlable URL');
        }
        try {
            $result = $this->head($url->getUrl());
            $report = new UrlReport($url, $result->getResponse()->getStatusCode());
        } catch (ClientException|RequestException $e) {
            $response = $e->getResponse();
            $report = new UrlReport($url, null === $response ? 0 : $response->getStatusCode(), $e->getMessage());
        }

        return $report;
    }

    public function getCacheFolder(): string
    {
        return $this->cacheFolder;
    }
}
