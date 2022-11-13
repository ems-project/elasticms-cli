<?php

declare(strict_types=1);

namespace App\Tests\WebToElasticms\Helper;

use App\Helper\TikaWrapper;
use GuzzleHttp\Psr7\BufferStream;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;

class TikaTest extends TestCase
{
    public function testLocales(): void
    {
        $tikaWrapper = new TikaWrapper(\sys_get_temp_dir());
        $streamFrench = new BufferStream();
        $streamFrench->write('Bonjour, comment allez-vous?');
        $streamDutch = new BufferStream();
        $streamDutch->write('Hoi, hoe gaat het met je vanmorgen?');
        $this->assertEquals('fr', $tikaWrapper->getLocale($streamFrench));
        $this->assertEquals('nl', $tikaWrapper->getLocale($streamDutch));
    }

    public function testWordFile(): void
    {
        $tikaWrapper = new TikaWrapper(\sys_get_temp_dir());
        $bonjourDocx = new Stream(\fopen(\join(DIRECTORY_SEPARATOR, [__DIR__, 'resources', 'Bonjour.docx']), 'r'));
        $this->assertEquals('fr', $tikaWrapper->getLocale($bonjourDocx));
        $this->assertEquals('Bonjour, comment allez-vous ? Voici un lien vers google. Bonne journée.', $tikaWrapper->getText($bonjourDocx, true));
        $json = $tikaWrapper->getJson($bonjourDocx);
        $this->assertEquals('Mathieu De Keyzer', $json['dc:creator'] ?? null);
        $this->assertEquals('Texte de test tika', $json['dc:title'] ?? null);
        $this->assertEquals(['https://www.google.com/'], $tikaWrapper->getLinks($bonjourDocx));
    }

    public function testPdfFile(): void
    {
        $tikaWrapper = new TikaWrapper(\sys_get_temp_dir());
        $bonjourDocx = new Stream(\fopen(\join(DIRECTORY_SEPARATOR, [__DIR__, 'resources', 'Bonjour.pdf']), 'r'));
        $this->assertEquals('fr', $tikaWrapper->getLocale($bonjourDocx));
        $this->assertEquals('Bonjour, comment allez-vous ? Voici un lien vers google. Bonne journée. https://www.google.com/', $tikaWrapper->getText($bonjourDocx, true));
        $this->assertEquals(['https://www.google.com/'], $tikaWrapper->getLinks($bonjourDocx));
    }
}
