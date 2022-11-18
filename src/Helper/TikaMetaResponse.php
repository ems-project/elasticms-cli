<?php

namespace App\Helper;

use GuzzleHttp\Promise\PromiseInterface;

class TikaMetaResponse
{
    private AsyncResponse $response;
    /** @var string[]|null */
    private ?array $meta = null;

    public function __construct(PromiseInterface $promise)
    {
        $this->response = new AsyncResponse($promise);
    }

    /**
     * @return string[]
     */
    public function getMeta(): array
    {
        if (null !== $this->meta) {
            return $this->meta;
        }
        $this->meta = $this->response->getJson();

        return $this->meta;
    }

    public function getLocale(): ?string
    {
        return $this->getMeta()['language'] ?? null;
    }
}
