<?php

declare(strict_types=1);

namespace App\Helper;

use EMS\CommonBundle\Common\Standard\Json;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class TikaWrapper
{
    public const BUFFER_SIZE = 8192;
    private string $tikaJar;

    public function __construct(string $cacheFolder)
    {
        $this->tikaJar = \join(DIRECTORY_SEPARATOR, [$cacheFolder, 'tika.jar']);
    }

    protected function run(string $option, StreamInterface $stream, bool $trimWhiteSpaces = false): string
    {
        $text = $stream->getContents();
        $this->initialize();
        $input = new InputStream();
        $process = new Process(['java', '-jar', $this->tikaJar, $option]);
        $process->setInput($input);
        $process->setWorkingDirectory(__DIR__);
        $process->start(function () {
        }, [
            'LANG' => 'en_US.utf-8',
        ]);

        if ($stream->isSeekable() && $stream->tell() > 0) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            $input->write($stream->read(self::BUFFER_SIZE));
        }
        $input->write($text);
        $input->close();
        $process->wait();

        if ($trimWhiteSpaces) {
            return \trim(\preg_replace('!\s+!', ' ', $process->getOutput()) ?? '');
        }

        return $process->getOutput();
    }

    public function getWordCount(StreamInterface $stream): int
    {
        return \str_word_count($this->getText($stream));
    }

    public function getXHTML(StreamInterface $stream): string
    {
        return $this->run('--xml', $stream);
    }

    public function getHTML(StreamInterface $stream): string
    {
        return $this->run('--html', $stream);
    }

    /**
     * @return string[]
     */
    public function getLinks(StreamInterface $stream): array
    {
        $html = $this->getHTML($stream);
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

    public function getText(StreamInterface $stream, bool $trimWhiteSpaces = false): string
    {
        $text = $this->run('--text', $stream, $trimWhiteSpaces);

        return $text;
    }

    public function getTextMain(StreamInterface $stream, bool $trimWhiteSpaces = false): string
    {
        return $this->run('--text-main', $stream, $trimWhiteSpaces);
    }

    public function getMetadata(StreamInterface $stream, bool $trimWhiteSpaces = false): string
    {
        return $this->run('--metadata', $stream, $trimWhiteSpaces);
    }

    /**
     * @return mixed[]
     */
    public function getJson(StreamInterface $stream): array
    {
        return Json::decode($this->run('--json', $stream));
    }

    public function getXmp(StreamInterface $stream): string
    {
        return $this->run('--xmp', $stream);
    }

    public function getLocale(StreamInterface $stream): string
    {
        return $this->run('--language', $stream, true);
    }

    public function getDocumentType(StreamInterface $stream): string
    {
        return $this->run('--detect', $stream, true);
    }

    private function initialize(): void
    {
        if (\file_exists($this->tikaJar)) {
            return;
        }
        \file_put_contents($this->tikaJar, \fopen('https://dlcdn.apache.org/tika/2.6.0/tika-app-2.6.0.jar', 'rb'));
    }
}
