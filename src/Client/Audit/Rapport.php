<?php

declare(strict_types=1);

namespace App\Client\Audit;

use App\Client\HttpClient\UrlReport;
use App\Client\WebToElasticms\Helper\Url;
use EMS\CommonBundle\Common\SpreadsheetGeneratorService;
use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;

class Rapport
{
    /** @var string[][] */
    private array $accessibilityErrors = [['URL', 'WCAG2AA', 'Accessibility\'s score']];
    /** @var string[][] */
    private array $securityErrors = [['URL', 'Missing headers', 'Best practice\'s score']];
    /** @var string[][] */
    private array $brokenLinks = [['Referer', 'URL', 'Status Code', 'Error message']];
    /** @var string[][] */
    private array $ignoredLinks = [['Referer', 'URL', 'Error message']];
    /** @var string[][] */
    private array $warnings = [['Referer', 'URL', 'Warning message']];
    private SpreadsheetGeneratorService $spreadsheetGeneratorService;

    public function __construct()
    {
        $this->spreadsheetGeneratorService = new SpreadsheetGeneratorService();
    }

    public function save(string $folder): void
    {
        $filename = $folder.DIRECTORY_SEPARATOR.\sprintf('Audit-Rapport-%s.xlsx', \date('Ymd-His'));
        $config = [
            SpreadsheetGeneratorServiceInterface::CONTENT_DISPOSITION => HeaderUtils::DISPOSITION_ATTACHMENT,
            SpreadsheetGeneratorServiceInterface::WRITER => SpreadsheetGeneratorServiceInterface::XLSX_WRITER,
            SpreadsheetGeneratorServiceInterface::CONTENT_FILENAME => 'Audit-Rapport.xlsx',
            SpreadsheetGeneratorServiceInterface::SHEETS => [
                [
                    'name' => 'Broken links',
                    'rows' => \array_values($this->brokenLinks),
                ],
                [
                    'name' => 'Ignored links',
                    'rows' => \array_values($this->ignoredLinks),
                ],
                [
                    'name' => 'Warnings',
                    'rows' => \array_values($this->warnings),
                ],
                [
                    'name' => 'Accessibility',
                    'rows' => \array_values($this->accessibilityErrors),
                ],
                [
                    'name' => 'Security',
                    'rows' => \array_values($this->securityErrors),
                ],
            ],
        ];
        $this->spreadsheetGeneratorService->generateSpreadsheetFile($config, $filename);
    }

    public function addAccessibilityError(string $url, int $errorCount, ?float $score): void
    {
        $this->accessibilityErrors[] = [$url, \strval($errorCount), null === $score ? '' : \strval($score)];
    }

    public function addSecurityError(string $url, int $count, ?float $score): void
    {
        $this->securityErrors[] = [$url, \strval($count), null === $score ? '' : \strval($score)];
    }

    public function addBrokenLink(UrlReport $urlReport): void
    {
        $this->brokenLinks[] = [$urlReport->getUrl()->getReferer() ?? '', $urlReport->getUrl()->getUrl(), \strval($urlReport->getStatusCode()), $urlReport->getMessage() ?? ''];
    }

    /**
     * @param string[] $warnings
     */
    public function addWarning(Url $url, array $warnings): void
    {
        foreach ($warnings as $warning) {
            $this->warnings[] = [$url->getReferer() ?? '', $url->getUrl(), $warning];
        }
    }

    /**
     * @return string[][]
     */
    public function getAccessibilityErrors(): array
    {
        return $this->accessibilityErrors;
    }

    /**
     * @param string[][] $accessibilityErrors
     */
    public function setAccessibilityErrors(array $accessibilityErrors): void
    {
        $this->accessibilityErrors = $accessibilityErrors;
    }

    /**
     * @return string[][]
     */
    public function getSecurityErrors(): array
    {
        return $this->securityErrors;
    }

    /**
     * @param string[][] $securityErrors
     */
    public function setSecurityErrors(array $securityErrors): void
    {
        $this->securityErrors = $securityErrors;
    }

    /**
     * @return string[][]
     */
    public function getBrokenLinks(): array
    {
        return $this->brokenLinks;
    }

    /**
     * @param string[][] $brokenLinks
     */
    public function setBrokenLinks(array $brokenLinks): void
    {
        $this->brokenLinks = $brokenLinks;
    }

    /**
     * @return string[][]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param string[][] $warnings
     */
    public function setWarnings(array $warnings): void
    {
        $this->warnings = $warnings;
    }

    public function addIgnoredUrl(Url $url, string $message): void
    {
        $this->ignoredLinks[] = [$url->getReferer() ?? '', $url->getUrl(), $message];
    }

    /**
     * @return string[][]
     */
    public function getIgnoredLinks(): array
    {
        return $this->ignoredLinks;
    }

    /**
     * @param string[][] $ignoredLinks
     */
    public function setIgnoredLinks(array $ignoredLinks): void
    {
        $this->ignoredLinks = $ignoredLinks;
    }
}
