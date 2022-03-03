<?php

declare(strict_types=1);

namespace App\Client\WebToElasticms\Filter\Html;

use App\Client\WebToElasticms\Config\ConfigManager;
use App\Client\WebToElasticms\Config\WebResource;
use Symfony\Component\DomCrawler\Crawler;

class TagCleaner implements HtmlInterface
{
    public const TYPE = 'tag-cleaner';
    private ConfigManager $config;

    public function __construct(ConfigManager $config)
    {
        $this->config = $config;
    }

    public function process(WebResource $resource, Crawler $content): void
    {
        foreach ($this->config->getCleanTags() as $cleanTag) {
            foreach ($content->filter($cleanTag) as $item) {
                if (!$item instanceof \DOMElement) {
                    throw new \RuntimeException('Unexpected non DOMElement object');
                }

                if ($item->parentNode instanceof \DOMElement) {
                    $item->parentNode->removeChild($item);
                }
            }
        }
    }
}
