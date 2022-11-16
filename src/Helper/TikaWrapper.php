<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Http\Message\StreamInterface;
use Symfony\Component\DomCrawler\Crawler;

class TikaWrapper extends ProcessWrapper
{
    private string $tikaJar;
    private bool $trimWhiteSpaces;

    private function __construct(StreamInterface $stream, string $option, string $cacheFolder, bool $trimWhiteSpaces = false, float $timeout = 3 * 60.0)
    {
        $this->tikaJar = \join(DIRECTORY_SEPARATOR, [$cacheFolder, 'tika.jar']);
        $this->trimWhiteSpaces = $trimWhiteSpaces;
        parent::__construct(['java', '-Djava.awt.headless=true', '-jar', $this->tikaJar, $option], $stream, $timeout);
    }

    public static function getLocale(StreamInterface $stream, string $cacheFolder, bool $trimWhiteSpaces = true): TikaWrapper
    {
        return new self($stream, '--language', $cacheFolder, $trimWhiteSpaces);
    }

    public static function getHtml(StreamInterface $stream, string $cacheFolder): TikaWrapper
    {
        return new self($stream, '--html', $cacheFolder);
    }

    public static function getText(StreamInterface $stream, string $cacheFolder, bool $trimWhiteSpaces = true): TikaWrapper
    {
        return new self($stream, '--text', $cacheFolder, $trimWhiteSpaces);
    }

    public static function getTextMain(StreamInterface $stream, string $cacheFolder, bool $trimWhiteSpaces = true): TikaWrapper
    {
        return new self($stream, '--text-main', $cacheFolder, $trimWhiteSpaces);
    }

    public static function getMetadata(StreamInterface $stream, string $cacheFolder, bool $trimWhiteSpaces = true): TikaWrapper
    {
        return new self($stream, '--metadata', $cacheFolder, $trimWhiteSpaces);
    }

    public static function getJsonMetadata(StreamInterface $stream, string $cacheFolder): TikaWrapper
    {
        return new self($stream, '--json', $cacheFolder);
    }

    public static function getDocumentType(StreamInterface $stream, string $cacheFolder): TikaWrapper
    {
        return new self($stream, '--detect', $cacheFolder, true);
    }

    public function getOutput(): string
    {
        if ($this->trimWhiteSpaces) {
            return \trim(\preg_replace('!\s+!', ' ', parent::getOutput()) ?? '');
        }

        return parent::getOutput();
    }

    protected function initialize(): void
    {
        if (\file_exists($this->tikaJar)) {
            return;
        }
        \file_put_contents($this->tikaJar, \fopen('https://dlcdn.apache.org/tika/2.6.0/tika-app-2.6.0.jar', 'rb'));
    }

    /**
     * @return string[]
     */
    public function getLinks(): array
    {
        $html = $this->getOutput();
        $crawler = new Crawler($html);
        $content = $crawler->filter('a');
        $externalLinks = [];
        for ($i = 0; $i < $content->count(); ++$i) {
            $item = $content->eq($i);
            $href = $item->attr('href');
            if (null === $href || 0 === \strlen($href) || '#' === \substr($href, 0, 1)) {
                continue;
            }
            $externalLinks[] = $href;
        }

        return $externalLinks;
    }
}
