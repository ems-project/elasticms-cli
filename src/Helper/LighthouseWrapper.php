<?php

declare(strict_types=1);

namespace App\Helper;

use EMS\Helpers\Standard\Json;
use Symfony\Component\Process\Process;

class LighthouseWrapper
{
    private ?string $output = null;
    private float $timeout;

    public function __construct(float $timeout = 5 * 60.0)
    {
        $this->timeout = $timeout;
    }

    public function run(string $url): LighthouseWrapper
    {
        $process = new Process([
            './node_modules/lighthouse/lighthouse-cli/index.js',
            $url,
            '--output=json',
            '--preset=desktop',
            '--quiet',
            '--only-categories=accessibility,best-practices,performance,seo',
            '--chrome-flags=\'--headless --disable-gpu --no-sandbox\'',
        ]);
        $process->setTimeout($this->timeout);
        $process->run(function () {
        }, [
            'LANG' => 'en_US.utf-8',
        ]);

        $this->output = $process->getOutput();

        return $this;
    }

    public function getOutput(): string
    {
        if (null === $this->output) {
            throw new \RuntimeException('Unexpected null Lighthouse\'s output');
        }

        return $this->output;
    }

    /**
     * @return mixed[]
     */
    public function getJson(): array
    {
        if (\in_array($this->output, [null, 'null', ''])) {
            return [];
        }

        return Json::decode($this->getOutput());
    }
}
