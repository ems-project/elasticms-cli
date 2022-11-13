<?php

declare(strict_types=1);

namespace App\Helper;

use EMS\Helpers\Standard\Json;
use Symfony\Component\Process\Process;

class Pa11yWrapper
{
    private float $timeout;
    private string $standard;
    private ?string $output = null;

    public function __construct(string $standard = 'WCAG2AA', float $timeout = 3 * 60.0)
    {
        $this->standard = $standard;
        $this->timeout = $timeout;
    }

    public function run(string $url): Pa11yWrapper
    {
        $process = new Process([
            './node_modules/pa11y/bin/pa11y.js',
            '-s',
            $this->standard,
            '-r',
            'json',
            $url,
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
            throw new \RuntimeException('Unexpected null pa11y\'s output');
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
