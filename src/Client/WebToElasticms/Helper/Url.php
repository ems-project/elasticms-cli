<?php

declare(strict_types=1);

namespace App\Client\WebToElasticms\Helper;

use Symfony\Component\OptionsResolver\OptionsResolver;

class Url
{
    private const ABSOLUTE_SCHEME = ['mailto', 'javascript'];
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
        $parsed = self::mb_parse_url($url);
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
        if (\in_array($this->getScheme(), self::ABSOLUTE_SCHEME)) {
            return $path;
        }
        if ('/' !== \substr($relativeToPath, \strlen($relativeToPath) - 1)) {
            $lastSlash = \strripos($relativeToPath, '/');
            if (false === $lastSlash) {
                $relativeToPath .= '/';
            } else {
                $relativeToPath = \substr($relativeToPath, 0, $lastSlash + 1);
            }
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
        if ('/' !== \substr($path, 0, 1)) {
            $path = '/'.$path;
        }

        return $path;
    }

    public function getUrl(string $path = null, bool $withFragment = false): string
    {
        if (null !== $path) {
            return (new Url($path, $this->getUrl()))->getUrl(null, $withFragment);
        }
        if (\in_array($this->getScheme(), self::ABSOLUTE_SCHEME)) {
            $url = \sprintf('%s:', $this->scheme);
        } elseif (null !== $this->user && null !== $this->password) {
            $url = \sprintf('%s://%s:%s@%s', $this->scheme, $this->user, $this->password, $this->host);
        } else {
            $url = \sprintf('%s://%s', $this->scheme, $this->host);
        }
        if (null !== $this->port) {
            $url = \sprintf('%s:%d%s', $url, $this->port, $this->path);
        } else {
            $url = \sprintf('%s%s', $url, $this->path);
        }
        if (null !== $this->query) {
            $url = \sprintf('%s?%s', $url, $this->query);
        }
        if ($withFragment && null !== $this->fragment) {
            $url = \sprintf('%s#%s', $url, $this->fragment);
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

    public function isCrawlable(): bool
    {
        return \in_array($this->getScheme(), ['http', 'https']);
    }

    public function getId(): string
    {
        return \sha1($this->getUrl());
    }

    /**
     * @return array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, query?: string, path?: string, fragment?: string}
     */
    public static function mb_parse_url(string $url): array
    {
        $enc_url = \preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            function ($matches) {
                return \urlencode($matches[0]);
            },
            $url
        );

        if (null === $enc_url) {
            throw new \RuntimeException(\sprintf('Unexpected wrong url %s', $url));
        }

        $parts = \parse_url($enc_url);

        if (false === $parts) {
            throw new \RuntimeException(\sprintf('Unexpected wrong url %s', $url));
        }

        foreach ($parts as $name => $value) {
            if (\is_int($value)) {
                continue;
            }
            $parts[$name] = \urldecode($value);
        }

        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults([
            'scheme' => null,
            'host' => null,
            'port' => null,
            'user' => null,
            'pass' => null,
            'path' => null,
            'query' => null,
            'fragment' => null,
        ]);
        $optionsResolver->setAllowedTypes('scheme', ['string', 'null']);
        $optionsResolver->setAllowedTypes('host', ['string', 'null']);
        $optionsResolver->setAllowedTypes('port', ['int', 'null']);
        $optionsResolver->setAllowedTypes('user', ['string', 'null']);
        $optionsResolver->setAllowedTypes('pass', ['string', 'null']);
        $optionsResolver->setAllowedTypes('path', ['string', 'null']);
        $optionsResolver->setAllowedTypes('query', ['string', 'null']);
        $optionsResolver->setAllowedTypes('fragment', ['string', 'null']);

        $resolved = $optionsResolver->resolve($parts);
        /* @var array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, query?: string, path?: string, fragment?: string} $resolved */
        return $resolved;
    }
}
