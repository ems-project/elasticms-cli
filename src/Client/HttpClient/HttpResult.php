<?php

declare(strict_types=1);

namespace App\Client\HttpClient;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class HttpResult
{
    private ResponseInterface $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getMimetype(): string
    {
        $mimeType = $this->response->getHeader('Content-Type');
        if (1 !== \count($mimeType)) {
            throw new \RuntimeException('Unexpected number of mime-type headers %d', \count($mimeType));
        }

        return $mimeType[0];
    }

    public function getStream(): StreamInterface
    {
        return $this->response->getBody();
    }

    public function isHtml(): bool
    {
        foreach (['text/html', 'text/xml', 'application/xhtml+xml', 'application/xml'] as $mimeType) {
            if (0 === \strpos($this->getMimetype(), $mimeType)) {
                return true;
            }
        }

        return false;
    }
}
