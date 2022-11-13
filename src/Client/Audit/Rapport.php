<?php

declare(strict_types=1);

namespace App\Client\Audit;

use App\Client\HttpClient\UrlReport;
use EMS\CommonBundle\Common\SpreadsheetGeneratorService;
use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;

class Rapport
{
    /** @var string[][] */
    private array $accessibilityErrors = [['URL', 'WCAG2AA']];
    /** @var string[][] */
    private array $securityErrors = [['URL', 'Missing headers']];
    /** @var string[][] */
    private array $brokenLinks = [['URL', 'Status Code', 'Error message', 'Referer']];
    /** @var string[][] */
    private array $warnings = [['URL', 'First warning', 'Warnings']];
    private string $filename;
    private SpreadsheetGeneratorService $spreadsheetGeneratorService;

    public function __construct(string $folder)
    {
        $this->filename = $folder.DIRECTORY_SEPARATOR.\sprintf('Audit-Rapport-%s.xlsx', \date('Ymd-His'));
        $this->spreadsheetGeneratorService = new SpreadsheetGeneratorService();
    }

    public function save(): void
    {
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
        $this->spreadsheetGeneratorService->generateSpreadsheetFile($config, $this->filename);
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
        $this->brokenLinks[] = [$urlReport->getUrl()->getUrl(), \strval($urlReport->getStatusCode()), $urlReport->getMessage() ?? '', $urlReport->getUrl()->getReferer() ?? ''];
    }

    /**
     * @param string[] $warning
     */
    public function addWarning(string $url, array $warning): void
    {
        $this->warnings[] = [$url, $warning[0] ?? '', \strval(\count($warning))];
    }
}
