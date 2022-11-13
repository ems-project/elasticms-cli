<?php

declare(strict_types=1);

namespace App\Client\Audit;

use App\Client\HttpClient\UrlReport;
use App\Client\WebToElasticms\Helper\Url;
use App\Helper\StringStream;
use EMS\CommonBundle\Common\CoreApi\Endpoint\File\File;

class AuditResult
{
    private Url $url;
    private string $hash;
    /** @var Url[] */
    private array $links = [];
    /** @var Url[] */
    private array $internalLinks = [];
    /** @var UrlReport[] */
    private array $externalLinks = [];
    /** @var SecurityWarning[] */
    private array $securityWarnings = [];
    private int $statusCode = 0;
    private ?string $errorMessage = null;
    /** @var mixed[] */
    private array $pa11y = [];
    /** @var string[] */
    private array $warnings = [];
    private ?string $lighthouseScreenshotBase64 = null;
    private ?string $lighthouseScreenshotMimetype = null;
    private ?float $performance = null;
    private ?float $seo = null;
    private ?string $lighthouseReport = null;
    private ?float $accessibility = null;
    private ?float $bestPractices = null;
    private ?string $mimetype;
    private \DateTimeImmutable $datetime;
    private ?string $locale = null;
    private ?string $content = null;
    private bool $valid = true;

    public function __construct(Url $url, string $hash)
    {
        $this->url = $url;
        $this->hash = $hash;
        $this->datetime = new \DateTimeImmutable();
    }

    public function getUrl(): Url
    {
        return $this->url;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return Url[]
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function addSecurityWaring(string $type, string $value): void
    {
        $this->securityWarnings[] = new SecurityWarning($type, $value);
    }

    /**
     * @param mixed[] $pa11y
     *
     * @return void
     */
    public function setPa11y(array $pa11y)
    {
        $this->pa11y = $pa11y;
    }

    /**
     * @return mixed[]
     */
    public function getPa11y(): array
    {
        return $this->pa11y;
    }

    public function setLighthouseScreenshot(string $data): void
    {
        $output_array = [];
        \preg_match('/data:(?P<mimetype>[a-z\/\-\+]+\/[a-z\/\-\+]+);base64,(?P<base64>.+)/', $data, $output_array);
        if (isset($output_array['mimetype']) && isset($output_array['base64']) && \is_string($output_array['mimetype']) && \is_string($output_array['base64'])) {
            $this->lighthouseScreenshotBase64 = $output_array['base64'];
            $this->lighthouseScreenshotMimetype = $output_array['mimetype'];
        }
    }

    /**
     * @return mixed[]
     */
    public function uploadAssets(File $fileApi): array
    {
        if (null === $this->lighthouseScreenshotMimetype || null === $this->lighthouseScreenshotBase64) {
            return [];
        }
        $stream = new StringStream(\base64_decode($this->lighthouseScreenshotBase64));
        $hash = $fileApi->uploadStream($stream, 'lighthouse-screenshot', $this->lighthouseScreenshotMimetype);

        return [
            'screenshot' => [
                'sha1' => $hash,
                'filename' => 'lighthouse-screenshot',
                'mimetype' => $this->lighthouseScreenshotMimetype,
            ],
        ];
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function setPerformance(float $score): void
    {
        $this->performance = $score;
    }

    public function setAccessibility(float $score): void
    {
        $this->accessibility = $score;
    }

    public function setBestPractices(float $score): void
    {
        $this->bestPractices = $score;
    }

    public function setSeo(float $score): void
    {
        $this->seo = $score;
    }

    public function setLighthouseReport(string $report): void
    {
        $this->lighthouseReport = $report;
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function setMimetype(string $mimetype): void
    {
        $this->mimetype = $mimetype;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function addLinks(Url $url): void
    {
        if (!$url->isCrawlable()) {
            return;
        }
        $this->links[$url->getId()] = $url;
    }

    /**
     * @param mixed[] $init
     *
     * @return mixed[]
     */
    public function getRawData(array $init): array
    {
        $security = [];
        foreach ($this->securityWarnings as $securityWarning) {
            $security[] = [
                'type' => $securityWarning->getType(),
                'value' => $securityWarning->getValue(),
            ];
        }
        $links = [];
        foreach ($this->externalLinks as $link) {
            $links[] = [
                'url' => $link->getUrl()->getUrl(),
                'message' => $link->getMessage(),
                'status_code' => $link->getStatusCode(),
            ];
        }

        return \array_filter(\array_merge($init, [
            'url' => $this->url->getUrl(),
            'referer' => $this->url->getReferer(),
            'pa11y' => $this->pa11y,
            'import_hash_resources' => $this->hash,
            'security' => $security,
            'status_code' => $this->statusCode,
            'warning' => $this->warnings[0] ?? null,
            'mimetype' => $this->mimetype,
            'error' => $this->errorMessage,
            'lighthouse_accessibility' => $this->accessibility,
            'lighthouse_performance' => $this->performance,
            'lighthouse_best-practices' => $this->bestPractices,
            'lighthouse_seo' => $this->seo,
            'lighthouse_report' => $this->lighthouseReport,
            'lighthouse_best-lighthouse_seo' => $this->bestPractices,
            'host' => $this->url->getHost(),
            'links' => $links,
            'locale' => $this->locale,
            'content' => $this->content,
            'timestamp' => $this->datetime->format('c'),
        ]), function ($k) {
            return null !== $k;
        });
    }

    public function addInternalLink(Url $link): void
    {
        $this->internalLinks[] = $link;
    }

    public function addExternalLink(UrlReport $testUrl): void
    {
        $this->externalLinks[] = $testUrl;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getUrlReport(): UrlReport
    {
        return new UrlReport($this->getUrl(), $this->getStatusCode(), $this->errorMessage);
    }

    /**
     * @return SecurityWarning[]
     */
    public function getSecurityWarnings(): array
    {
        return $this->securityWarnings;
    }

    public function getAccessibility(): ?float
    {
        return $this->accessibility;
    }

    public function getBestPractices(): ?float
    {
        return $this->bestPractices;
    }
}
