<?php

declare(strict_types=1);

namespace App\Client\WebToElasticms\Extract;

use App\Client\HttpClient\HttpResult;
use App\Client\WebToElasticms\Config\Analyzer;
use App\Client\WebToElasticms\Config\WebResource;

interface ExtractorInterface
{
    /**
     * @param mixed[] $data
     */
    public function extractData(WebResource $resource, HttpResult $result, Analyzer $analyzer, array &$data): void;
}
