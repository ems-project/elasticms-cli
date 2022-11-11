<?php

declare(strict_types=1);

namespace App\Client\WebToElasticms\Extract;

use App\Client\HttpClient\HttpResult;
use App\Client\WebToElasticms\Config\Analyzer;
use App\Client\WebToElasticms\Config\ConfigManager;
use App\Client\WebToElasticms\Config\Document;
use App\Client\WebToElasticms\Config\WebResource;
use App\Client\WebToElasticms\Rapport\Rapport;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class Bin implements ExtractorInterface
{
    public const TYPE = 'bin';
    private ConfigManager $config;
    private Document $document;
    private LoggerInterface $logger;
    private Rapport $rapport;

    public function __construct(ConfigManager $config, Document $document, LoggerInterface $logger, Rapport $rapport)
    {
        $this->config = $config;
        $this->document = $document;
        $this->logger = $logger;
        $this->rapport = $rapport;
    }

    /**
     * @param array<mixed> $data
     */
    public function extractData(WebResource $resource, HttpResult $result, Analyzer $analyzer, array &$data): void
    {
        $stream = $result->getResponse()->getBody();
        $stream->rewind();
        foreach ($analyzer->getExtractors() as $extractor) {
            foreach ($extractor->getFilters() as $filter) {
                $stream = $this->applyFilter($filter, $stream);
            }

            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $property = \str_replace(['%locale%'], [$resource->getLocale()], $extractor->getProperty());
            $propertyAccessor->setValue($data, $property, $stream->getContents());
        }
    }

    private function applyFilter(string $filter, StreamInterface $stream): StreamInterface
    {
        return $this->textToStream('foobar');
    }
}
