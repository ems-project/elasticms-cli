<?php

declare(strict_types=1);

namespace App\Tests\WebToElasticms\Helper;

use App\Client\WebToElasticms\Helper\Url;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    public function testUrl(): void
    {
        $this->assertEquals('https://google.com/', (new Url('https://google.com'))->getUrl());
        $this->assertEquals('https://user:password@google.com/', (new Url('https://user:password@google.com'))->getUrl());
        $this->assertEquals('https://user:password@google.com/toto.txt', (new Url('/aa/../bb/vv/../../toto.txt', 'https://user:password@google.com'))->getUrl());
        $this->assertEquals('https://user:password@google.com/toto.txt', (new Url('./toto.txt', 'https://user:password@google.com/aaa/../'))->getUrl());
        $this->assertEquals('https://user:password@google.com/aaa/toto.txt', (new Url('./toto.txt', 'https://user:password@google.com/aaa'))->getUrl());
        $this->assertEquals('https://user:password@google.com/aaa/toto.txt', (new Url('./toto.txt', 'https://user:password@google.com/aaa/'))->getUrl());
        $this->assertEquals('https://user:password@google.com/aaa/toto.txt#anchor', (new Url('./toto.txt#anchor', 'https://user:password@google.com/aaa/'))->getUrl());
        $this->assertEquals('https://user:password@google.com/aaa/toto.txt?anchor=toto&foo=bar', (new Url('./toto.txt?anchor=toto&foo=bar', 'https://user:password@google.com/aaa/'))->getUrl());
        $this->assertEquals('https://user:password@google.com/aaa/toto.txt#anchor?anchor=toto&foo=bar', (new Url('./toto.txt#anchor?anchor=toto&foo=bar', 'https://user:password@google.com/aaa/'))->getUrl());
        $this->assertEquals('https://user:password@google.com/aaa/toto.txt', (new Url('./toto.txt', 'https://user:password@google.com/aaa/#anchor?anchor=toto&foo=bar'))->getUrl());
    }

    public function testFilename(): void
    {
        $this->assertEquals('index.html', (new Url('https://google.com'))->getFilename());
        $this->assertEquals('index.html', (new Url('https://user:password@google.com'))->getFilename());
        $this->assertEquals('toto.txt', (new Url('/aa/../bb/vv/../../toto.txt', 'https://user:password@google.com'))->getFilename());
        $this->assertEquals('toto.txt', (new Url('./toto.txt', 'https://user:password@google.com/aaa/../'))->getFilename());
        $this->assertEquals('toto.txt', (new Url('./toto.txt', 'https://user:password@google.com/aaa'))->getFilename());
        $this->assertEquals('toto.txt', (new Url('./toto.txt', 'https://user:password@google.com/aaa/'))->getFilename());
        $this->assertEquals('toto.txt', (new Url('./toto.txt#anchor', 'https://user:password@google.com/aaa/'))->getFilename());
        $this->assertEquals('toto.txt', (new Url('./toto.txt?anchor=toto&foo=bar', 'https://user:password@google.com/aaa/'))->getFilename());
        $this->assertEquals('toto.txt', (new Url('./toto.txt#anchor?anchor=toto&foo=bar', 'https://user:password@google.com/aaa/'))->getFilename());
        $this->assertEquals('toto.txt', (new Url('./toto.txt', 'https://user:password@google.com/aaa/#anchor?anchor=toto&foo=bar'))->getFilename());
    }
}
