<?php

declare(strict_types=1);

namespace App\Client\WebToElasticms\Config;

use App\Client\WebToElasticms\Cache\CacheManager;
use App\Client\WebToElasticms\Helper\Url;
use App\Client\WebToElasticms\Rapport\Rapport;
use EMS\CommonBundle\Common\CoreApi\CoreApi;
use EMS\CommonBundle\Helper\EmsFields;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ExpressionLanguage;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ConfigManager
{
    /** @var Document[] */
    private array $documents;

    /** @var Analyzer[] */
    private array $analyzers;

    /** @var Type[] */
    private array $types;

    /** @var string[] */
    private array $hosts = [];

    /** @var string[] */
    private $validClasses = [];
    /** @var string[] */
    private $locales = [];
    /** @var string[] */
    private $linkToClean = [];
    private CacheManager $cacheManager;
    private CoreApi $coreApi;
    private LoggerInterface $logger;
    private ?ExpressionLanguage $expressionLanguage = null;
    private string $hashResourcesField = 'import_hash_resources';
    private ?string $autoDiscoverResourcesLink = null;
    private ?string $ignoreResourceLinkPattern = null;
    /** @var string[] */
    private array $linksByUrl = [];
    /**
     * @var array<string, string[]>
     */
    private array $documentsToClean = [];
    private ?string $lastUpdated = null;

    public function serialize(string $format = JsonEncoder::FORMAT): string
    {
        return self::getSerializer()->serialize($this, $format);
    }

    public static function deserialize(string $data, CacheManager $cache, CoreApi $coreApi, LoggerInterface $logger, string $format = JsonEncoder::FORMAT): ConfigManager
    {
        $config = self::getSerializer()->deserialize($data, ConfigManager::class, $format);
        if (!$config instanceof ConfigManager) {
            throw new \RuntimeException('Unexpected non ConfigManager object');
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

    /**
     * @return Document[]
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @param Document[] $documents
     */
    public function setDocuments(array $documents): void
    {
        $this->documents = $documents;
    }

    /**
     * @return Analyzer[]
     */
    public function getAnalyzers(): array
    {
        return $this->analyzers;
    }

    /**
     * @param Analyzer[] $analyzers
     */
    public function setAnalyzers(array $analyzers): void
    {
        $this->analyzers = $analyzers;
    }

    public function getAnalyzer(string $analyzerName): Analyzer
    {
        foreach ($this->analyzers as $analyzer) {
            if ($analyzer->getName() === $analyzerName) {
                return $analyzer;
            }
        }

        throw new \RuntimeException(\sprintf('Analyzer %s not found', $analyzerName));
    }

    /**
     * @return string[]
     */
    public function getHosts(): array
    {
        if (empty($this->hosts)) {
            foreach ($this->documents as $document) {
                foreach ($document->getResources() as $resource) {
                    $url = new Url($resource->getUrl());
                    if (!\in_array($url->getHost(), $this->hosts)) {
                        $this->hosts[] = $url->getHost();
                    }
                }
            }
        }

        return $this->hosts;
    }

    /**
     * @param string[] $hosts
     */
    public function setHosts(array $hosts): void
    {
        $this->hosts = $hosts;
    }

    public function findInternalLink(Url $url, Rapport $rapport): string
    {
        if (isset($this->linksByUrl[$url->getPath()])) {
            return $this->linksByUrl[$url->getPath()];
        }

        if ($rapport->inUrlsNotFounds($url)) {
            return $url->getPath();
        }

        $path = $this->findInDocuments($url);
        if (null === $path) {
            $path = $this->downloadAsset($url);
        }
        if (null === $path) {
            $path = $url->getPath();
            $rapport->addUrlNotFound($url);
        }

        if (null !== $url->getFragment()) {
            $path .= '#'.$url->getFragment();
        }
        if (null !== $url->getQuery()) {
            $path .= '?'.$url->getQuery();
        }

        return $path;
    }

    /**
     * @return string[]
     */
    public function getValidClasses(): array
    {
        return $this->validClasses;
    }

    /**
     * @param string[] $validClasses
     */
    public function setValidClasses(array $validClasses): void
    {
        $this->validClasses = $validClasses;
    }

    private function findInDocuments(Url $url): ?string
    {
        foreach ($this->documents as $document) {
            $ouuid = $document->getOuuid();
            foreach ($document->getResources() as $resource) {
                $resourceUrl = new Url($resource->getUrl());
                if ($resourceUrl->getPath() === $url->getPath()) {
                    return \sprintf('ems://object:%s:%s', $document->getType(), $ouuid);
                }
            }
        }

        return null;
    }

    /**
     * @return array{filename: string, filesize: int|null, mimetype: string, sha1: string}|array{}
     */
    public function urlToAssetArray(Url $url): array
    {
        $asset = $this->cacheManager->get($url->getUrl());
        $mimeType = $asset->getMimetype();
        if (200 != $asset->getResponse()->getStatusCode() || false !== \strpos($mimeType, 'text/html')) {
            return [];
        }
        $filename = $url->getFilename();
        $hash = $this->coreApi->file()->uploadStream($asset->getStream(), $filename, $mimeType);

        if (null === $hash) {
            throw new \RuntimeException('Unexpected null hash');
        }

        return [
            EmsFields::CONTENT_FILE_HASH_FIELD => $hash,
            EmsFields::CONTENT_FILE_NAME_FIELD => $filename,
            EmsFields::CONTENT_MIME_TYPE_FIELD => $mimeType,
            EmsFields::CONTENT_FILE_SIZE_FIELD => $asset->getStream()->getSize(),
        ];
    }

    private function downloadAsset(Url $url): ?string
    {
        $assetArray = $this->urlToAssetArray($url);
        if (empty($assetArray)) {
            return null;
        }

        return \sprintf('ems://asset:%s?name=%s&type=%s', $assetArray[EmsFields::CONTENT_FILE_HASH_FIELD], \urlencode($assetArray[EmsFields::CONTENT_FILE_NAME_FIELD]), \urlencode($assetArray[EmsFields::CONTENT_MIME_TYPE_FIELD]));
    }

    /**
     * @return string[]
     */
    public function getLinkToClean(): array
    {
        return $this->linkToClean;
    }

    /**
     * @param string[] $linkToClean
     */
    public function setLinkToClean(array $linkToClean): void
    {
        $this->linkToClean = $linkToClean;
    }

    /**
     * @return Type[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param Type[] $types
     */
    public function setTypes(array $types): void
    {
        $this->types = $types;
    }

    public function getType(string $name): Type
    {
        foreach ($this->types as $type) {
            if ($type->getName() === $name) {
                return $type;
            }
        }

        throw new \RuntimeException(\sprintf('Type %s not found', $name));
    }

    public function save(string $jsonPath, bool $finish = false): bool
    {
        if ($finish) {
            $this->lastUpdated = null;
        }

        return false !== \file_put_contents($jsonPath, $this->serialize());
    }

    public function getExpressionLanguage(): ExpressionLanguage
    {
        if (null !== $this->expressionLanguage) {
            return $this->expressionLanguage;
        }
        $this->expressionLanguage = new ExpressionLanguage();

        $this->expressionLanguage->register('uuid', function () {
            return '(\\Ramsey\\Uuid\\Uuid::uuid4()->toString())';
        }, function ($arguments) {
            return \Ramsey\Uuid\Uuid::uuid4()->toString();
        });

        $this->expressionLanguage->register('json_escape', function ($str) {
            return \sprintf('(null === %1$s ? null : \\EMS\\CommonBundle\\Common\\Standard\\Json::escape(%1$s))', $str);
        }, function ($arguments, $str) {
            return null === $str ? null : \EMS\CommonBundle\Common\Standard\Json::escape($str);
        });

        $this->expressionLanguage->register('strtotime', function ($str) {
            return \sprintf('(null === %1$s ? null : \\strtotime(%1$s))', $str);
        }, function ($arguments, $str) {
            return null === $str ? null : \strtotime($str);
        });

        $this->expressionLanguage->register('date', function ($format, $timestamp) {
            return \sprintf('((null === %1$s || null === %2$s) ? null : \\date(%1$s, %2$s))', $format, $timestamp);
        }, function ($arguments, $format, $timestamp) {
            return (null === $format || null === $timestamp) ? null : \date($format, $timestamp);
        });

        return $this->expressionLanguage;
    }

    public function getHashResourcesField(): string
    {
        return $this->hashResourcesField;
    }

    public function setHashResourcesField(string $hashResourcesField): void
    {
        $this->hashResourcesField = $hashResourcesField;
    }

    /**
     * @return string[]
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * @param string[] $locales
     */
    public function setLocales(array $locales): void
    {
        $this->locales = $locales;
    }

    public function getAutoDiscoverResourcesLink(): ?string
    {
        return $this->autoDiscoverResourcesLink;
    }

    public function setAutoDiscoverResourcesLink(?string $autoDiscoverResourcesLink): void
    {
        $this->autoDiscoverResourcesLink = $autoDiscoverResourcesLink;
    }

    public function getIgnoreResourceLinkPattern(): ?string
    {
        return $this->ignoreResourceLinkPattern;
    }

    public function setIgnoreResourceLinkPattern(?string $ignoreResourceLinkPattern): void
    {
        $this->ignoreResourceLinkPattern = $ignoreResourceLinkPattern;
    }

    /**
     * @return string[]
     */
    public function getLinksByUrl(): array
    {
        return $this->linksByUrl;
    }

    /**
     * @param string[] $linksByUrl
     */
    public function setLinksByUrl(array $linksByUrl): void
    {
        $this->linksByUrl = $linksByUrl;
    }

    /**
     * @return array<string, string[]>
     */
    public function getDocumentsToClean(): array
    {
        return $this->documentsToClean;
    }

    /**
     * @param array<string, string[]> $documentsToClean
     */
    public function setDocumentsToClean(array $documentsToClean): void
    {
        $this->documentsToClean = $documentsToClean;
    }

    public function getLastUpdated(): ?string
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?string $lastUpdated): void
    {
        $this->lastUpdated = $lastUpdated;
    }
}
