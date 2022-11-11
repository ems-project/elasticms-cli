<?php

declare(strict_types=1);

namespace App\Client\Audit;

use App\Client\HttpClient\CacheManager;
use App\Client\WebToElasticms\Helper\Url;
use EMS\CommonBundle\Contracts\CoreApi\CoreApiInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Cache
{
    private CacheManager $cacheManager;
    private Url $baseUrl;
    /** @var string[] */
    private array $urls;
    /** @var string[] */
    private array $hosts;
    private CoreApiInterface $coreApi;
    private LoggerInterface $logger;
    private ?int $lastUpdated = null;
    private int $current = -1;

    public function __construct(?CacheManager $cacheManager = null, ?Url $baseUrl = null, ?CoreApiInterface $coreApi = null, ?LoggerInterface $logger = null)
    {
        if (null !== $coreApi) {
            $this->coreApi = $coreApi;
        }
        if (null !== $logger) {
            $this->logger = $logger;
        }
        if (null !== $cacheManager) {
            $this->cacheManager = $cacheManager;
        }
        if (null !== $baseUrl) {
            $this->baseUrl = $baseUrl;
            $this->urls = [$baseUrl->getUrl()];
            $this->hosts = [$baseUrl->getHost()];
        } else {
            $this->urls = [];
        }
    }

    public function serialize(string $format = JsonEncoder::FORMAT): string
    {
        return self::getSerializer()->serialize($this, $format);
    }

    public static function deserialize(string $data, CacheManager $cache, CoreApiInterface $coreApi, LoggerInterface $logger, string $format = JsonEncoder::FORMAT): Cache
    {
        $config = self::getSerializer()->deserialize($data, Cache::class, $format);
        if (!$config instanceof Cache) {
            throw new \RuntimeException('Unexpected non Cache object');
        }
        $config->cacheManager = $cache;
        $config->coreApi = $coreApi;
        $config->logger = $logger;

        return $config;
    }

    private static function getSerializer(): Serializer
    {
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();
        $propertyTypeExtractor = new PropertyInfoExtractor([$reflectionExtractor], [$phpDocExtractor, $reflectionExtractor], [$phpDocExtractor], [$reflectionExtractor], [$reflectionExtractor]);

        return new Serializer([
            new ArrayDenormalizer(),
            new ObjectNormalizer(null, null, null, $propertyTypeExtractor),
        ], [
            new XmlEncoder(),
            new JsonEncoder(new JsonEncode([JsonEncode::OPTIONS => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES]), null),
        ]);
    }

    public function save(string $jsonPath, bool $finish = false): bool
    {
        if ($finish) {
            $this->lastUpdated = null;
        }

        return false !== \file_put_contents($jsonPath, $this->serialize());
    }

    /**
     * @return string[]
     */
    public function getUrls(): array
    {
        return $this->urls;
    }

    /**
     * @param string[] $urls
     */
    public function setUrls(array $urls): void
    {
        $this->urls = $urls;
    }

    public function getLastUpdated(): ?int
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?int $lastUpdated): void
    {
        $this->lastUpdated = $lastUpdated;
    }

    public function hasNext(): bool
    {
        return isset($this->urls[$this->current + 1]);
    }

    public function next(): string
    {
        ++$this->current;

        return $this->current();
    }

    public function current(): string
    {
        if (!isset($this->urls[$this->current])) {
            throw new \RuntimeException('Missing next url');
        }

        return $this->urls[$this->current];
    }

    /**
     * @return string[]
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /**
     * @param string[] $hosts
     */
    public function setHosts(array $hosts): void
    {
        $this->hosts = $hosts;
    }

    public function addUrl(string $url): void
    {
        if (\in_array($url, $this->urls)) {
            return;
        }
        $this->urls[] = $url;
    }
}
