<?php

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

    public function run(string $url): void
    {
        $process = new Process(['pa11y', '-s', $this->standard, '-r', 'json', $url]);
        $process->setTimeout($this->timeout);
        $process->setWorkingDirectory(__DIR__);
        $process->run(function () {
        }, [
            'LANG' => 'en_US.utf-8',
        ]);

        $this->output = $process->getOutput();
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
        return Json::decode($this->getOutput());
    }
}
