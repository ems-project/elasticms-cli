<?php

declare(strict_types=1);

namespace App\Tests\WebToElasticms\Helper;

use App\Helper\StringStream;
use App\Helper\TikaWrapper;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;

class TikaTest extends TestCase
{
    public function testLocales(): void
    {
        $tikaWrapper = new TikaWrapper(\sys_get_temp_dir());
        $this->assertEquals('fr', $tikaWrapper->getLocale(new StringStream('Bonjour, comment allez-vous?')));
        $this->assertEquals('nl', $tikaWrapper->getLocale(new StringStream('Hoi, hoe gaat het met je vanmorgen?')));
    }

    public function testWordFile(): void
    {
        $tikaWrapper = new TikaWrapper(\sys_get_temp_dir());
        $bonjourDocx = new Stream(\fopen(\join(DIRECTORY_SEPARATOR, [__DIR__, 'resources', 'Bonjour.docx']), 'r'));
        $this->assertEquals('fr', $tikaWrapper->getLocale($bonjourDocx));
        $this->assertEquals('Bonjour, comment allez-vous ? Voici un lien vers google. Bonne journée.', \trim(\preg_replace('!\s+!', ' ', $tikaWrapper->getText($bonjourDocx))));
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
        $this->assertEquals('Bonjour, comment allez-vous ? Voici un lien vers google. Bonne journée. https://www.google.com/', \trim(\preg_replace('!\s+!', ' ', $tikaWrapper->getText($bonjourDocx))));
        $this->assertEquals(['https://www.google.com/'], $tikaWrapper->getLinks($bonjourDocx));
    }
}
