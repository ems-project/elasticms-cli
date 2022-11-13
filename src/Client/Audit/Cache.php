<?php

declare(strict_types=1);

namespace App\Client\Audit;

use App\Client\WebToElasticms\Helper\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
    /** @var array<string, Url> */
    private array $urls = [];
    /** @var string[] */
    private array $hosts = [];
    private LoggerInterface $logger;
    private ?string $lastUpdated = null;
    private ?string $current = null;
    private ?string $status = null;

    public function __construct(?Url $baseUrl = null, ?LoggerInterface $logger = null)
    {
        if (null !== $logger) {
            $this->logger = $logger;
        }
        if (null !== $baseUrl) {
            $this->addUrl($baseUrl);
        } else {
            $this->urls = [];
        }
    }

    public function serialize(string $format = JsonEncoder::FORMAT): string
    {
        return self::getSerializer()->serialize($this, $format);
    }

    public static function deserialize(string $data, LoggerInterface $logger, string $format = JsonEncoder::FORMAT): Cache
    {
        $config = self::getSerializer()->deserialize($data, Cache::class, $format);
        if (!$config instanceof Cache) {
            throw new \RuntimeException('Unexpected non Cache object');
        }
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
     * @return Url[]
     */
    public function getUrls(): array
    {
        return $this->urls;
    }

    /**
     * @param Url[] $urls
     */
    public function setUrls(array $urls): void
    {
        $this->urls = $urls;
    }

    public function getLastUpdated(): ?string
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?string $lastUpdated): void
    {
        $this->lastUpdated = $lastUpdated;
    }

    public function hasNext(): bool
    {
        return null !== $this->nextId();
    }

    public function next(): Url
    {
        $this->lastUpdated = $this->current;
        $this->current = $this->nextId();

        return $this->current();
    }

    public function current(): Url
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

    public function addUrl(Url $url): void
    {
        if (isset($this->urls[$url->getId()])) {
            return;
        }
        if (!\in_array($url->getHost(), $this->hosts)) {
            $this->hosts[] = $url->getHost();
        }
        $this->urls[$url->getId()] = $url;
    }

    public function progress(OutputInterface $output): void
    {
        $this->rewindOutput($output);
        $currentPosition = $this->currentPos();
        $this->status = \sprintf('%d urls audited, %d urls pending, %d urls found', +1, \count($this->urls) - $currentPosition - 1, \count($this->urls));
        $output->write($this->status);
    }

    public function progressFinish(OutputInterface $output, int $counter): void
    {
        $this->rewindOutput($output);
        $this->status = null;
        $output->writeln(\sprintf('%d/%d urls have been audited', $counter, \count($this->urls)));
    }

    protected function rewindOutput(OutputInterface $output): void
    {
        if (null !== $this->status) {
            $output->write(\sprintf("\033[%dD", \strlen($this->status)));
        }
    }

    public function reset(): void
    {
        if (null !== $this->lastUpdated) {
            $this->current = $this->lastUpdated;
        }
    }

    private function nextId(): ?string
    {
        $keys = \array_keys($this->urls);
        if (null === $this->current) {
            return $keys[0] ?? null;
        }
        $currentPos = \array_search($this->current, $keys, true);
        if (false === $currentPos) {
            throw new \RuntimeException(\sprintf('Current position %s not found', $this->current ?? 'null'));
        }
        $nextPos = $currentPos + 1;

        return $keys[$nextPos] ?? null;
    }

    private function currentPos(): int
    {
        $keys = \array_keys($this->urls);
        $position = \array_search($this->current, $keys);

        return $position ?: 0;
    }

    public function inHosts(string $host): bool
    {
        return \in_array($host, $this->hosts);
    }

    /**
     * @param string[] $hosts
     */
    public function setHosts(array $hosts): void
    {
        $this->hosts = $hosts;
    }
}
