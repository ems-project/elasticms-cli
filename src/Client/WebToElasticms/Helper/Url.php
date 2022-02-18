<?php

declare(strict_types=1);

namespace App\Client\WebToElasticms\Helper;

class Url
{
    private string $scheme;
    private string $host;
    private ?int $port;
    private ?string $user;
    private ?string $password;
    private string $path;
    private ?string $query;
    private ?string $fragment;
    private ?string $referer;

    public function __construct(string $url, string $referer = null)
    {
        $parsed = \parse_url($url);
        if (false === $parsed) {
            throw new \RuntimeException(\sprintf('Unexpected wrong url %s', $url));
        }

        $relativeParsed = [];
        if (null !== $referer) {
            $relativeParsed = \parse_url($referer);
        }
        if (false === $relativeParsed) {
            throw new \RuntimeException(\sprintf('Unexpected wrong url %s', $referer));
        }

        $scheme = $parsed['scheme'] ?? $relativeParsed['scheme'] ?? null;
        if (null === $scheme) {
            throw new \RuntimeException('Unexpected null scheme');
        }
        $this->scheme = $scheme;

        $host = $parsed['host'] ?? $relativeParsed['host'] ?? null;
        if (null === $host) {
            throw new \RuntimeException('Unexpected null host');
        }
        $this->host = $host;

        $this->referer = $referer;
        $this->user = $parsed['user'] ?? $relativeParsed['user'] ?? null;
        $this->password = $parsed['pass'] ?? $relativeParsed['pass'] ?? null;
        $this->port = $parsed['port'] ?? $relativeParsed['port'] ?? null;
        $this->query = $parsed['query'] ?? null;
        $this->fragment = $parsed['fragment'] ?? null;

        $this->path = $this->getAbsolutePath($parsed['path'] ?? '/', $relativeParsed['path'] ?? '/');
    }

    private function getAbsolutePath(string $path, string $relativeToPath): string
    {
        if ('/' !== \substr($relativeToPath, \strlen($relativeToPath) - 1)) {
            $relativeToPath .= '/';
        }

        if ('.' === \substr($path, 0, 1)) {
            $path = $relativeToPath.$path;
        }
        $patterns = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
        for ($n = 1; $n > 0;) {
            $path = \preg_replace($patterns, '/', $path, -1, $n);
            if (!\is_string($path)) {
                throw new \RuntimeException(\sprintf('Unexpected non string path %s', $path));
            }
        }

        return $path;
    }

    public function getUrl(): string
    {
        if (null !== $this->user && null !== $this->password) {
            $url = \sprintf('%s://%s:%s@%s', $this->scheme, $this->user, $this->password, $this->host);
        } else {
            $url = \sprintf('%s://%s', $this->scheme, $this->host);
        }
        if (null !== $this->port) {
            $url = \sprintf('%s:%d%s', $url, $this->port, $this->path);
        } else {
            $url = \sprintf('%s%s', $url, $this->path);
        }
        if (null !== $this->fragment) {
            $url = \sprintf('%s#%s', $url, $this->fragment);
        }
        if (null !== $this->query) {
            $url = \sprintf('%s?%s', $url, $this->query);
        }

        return $url;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function getFragment(): ?string
    {
        return $this->fragment;
    }

    public function getFilename(): string
    {
        $exploded = \explode('/', $this->path);
        $name = \array_pop($exploded);
        if ('' === $name) {
            return 'index.html';
        }

        return $name;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }
}
