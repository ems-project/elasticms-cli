<?php

declare(strict_types=1);

namespace App\Tests\ExpressionLanguage;

use App\ExpressionLanguage\Functions;
use EMS\CommonBundle\Common\Standard\Json;
use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase
{
    public function testDomToJsonMenu()
    {
        $html = '<p>Coucou</p> <h2>Titre</h2> toto <p>foobar</p>';
        $splitted = Functions::domToJsonMenu($html, 'h2', 'body', 'paragraph', 'title');

        $json = Json::decode($splitted);
        $this->assertEquals(2, \count($json));
        $this->assertEquals('Titre', $json[1]['object']['title']);
        $this->assertEquals('Titre', $json[1]['label']);
        $this->assertEquals('Titre', $json[1]['object']['label']);
        $this->assertEquals('<p>Coucou</p> ', $json[0]['object']['body']);
        $this->assertEquals(' toto <p>foobar</p>', $json[1]['object']['body']);
    }
}
