<?php

declare(strict_types=1);

namespace App\Client\WebToElasticms\Config;

use App\Client\WebToElasticms\Helper\Url;

class DocumentResources
{
    /** @var WebResource[] */
    private array $resources;

    /**
     * @param WebResource[] $resources
     */
    public function __construct(array $resources)
    {
        $this->resources = $resources;
    }

    public function getPathFor(string $locale): ?string
    {
        foreach ($this->resources as $resource) {
            if ($resource->getLocale() === $locale) {
                $url = new Url($resource->getUrl());

                return $url->getPath();
            }
        }

        return null;
    }
}
