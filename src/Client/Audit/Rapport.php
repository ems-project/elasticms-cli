<?php

declare(strict_types=1);

namespace App\Client\Audit;

use EMS\CommonBundle\Common\SpreadsheetGeneratorService;
use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;

class Rapport
{
    /** @var string[][] */
    private array $accessibilityErrors = [['URL', 'WCAG-AA']];
    /** @var string[][] */
    private array $securityErrors = [['URL', 'Missing headers']];
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

    public function addAccessibilityError(string $url, int $errorCount): void
    {
        $this->accessibilityErrors[] = [$url, \strval($errorCount)];
    }

    public function addSecurityError(string $url, int $count): void
    {
        $this->securityErrors[] = [$url, \strval($count)];
    }
}
