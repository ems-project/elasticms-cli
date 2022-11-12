<?php

namespace App\Client\Audit;

use App\Client\HttpClient\HttpResult;
use App\Client\HttpClient\UrlReport;
use App\Client\WebToElasticms\Helper\Url;
use App\Helper\LighthouseWrapper;
use App\Helper\Pa11yWrapper;
use App\Helper\StringStream;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Contracts\CoreApi\Endpoint\Data\DataInterface;
use EMS\CommonBundle\Contracts\CoreApi\Endpoint\File\FileInterface;
use Psr\Log\LoggerInterface;

class AuditManager
{
    private DataInterface $dataApi;
    private Rapport $rapport;
    private LoggerInterface $logger;
    private bool $dryRun;
    private Pa11yWrapper $pa11yWrapper;
    private bool $lighthouse;
    private bool $pa11y;
    private FileInterface $fileApi;

    public function __construct(DataInterface $dataApi, FileInterface $fileApi, Rapport $rapport, LoggerInterface $logger, bool $dryRun, bool $pa11y, bool $lighthouse)
    {
        $this->dataApi = $dataApi;
        $this->fileApi = $fileApi;
        $this->rapport = $rapport;
        $this->logger = $logger;
        $this->dryRun = $dryRun;
        $this->pa11y = $pa11y;
        $this->lighthouse = $lighthouse;
        $this->pa11yWrapper = new Pa11yWrapper();
    }

    /**
     * @param UrlReport[] $externalLinks
     */
    public function analyze(string $url, HttpResult $result, string $hash, array $externalLinks): void
    {
        $data = [
            'import_hash_resources' => $hash,
        ];
        foreach ($externalLinks as $link) {
            $data['links'][] = [
                'url' => $link->getUrl()->getUrl(),
                'message' => $link->getMessage(),
                'status_code' => $link->getStatusCode(),
            ];
        }
        $this->auditSecurity($url, $data, $result);
        if ($this->pa11y) {
            $this->auditAccessibility($url, $data, $result);
        }
        if ($this->lighthouse) {
            $this->auditLighthouse($url, $data);
        }
        $this->info($url, $data, $result);

        if ($this->dryRun) {
            $this->logger->notice(Json::encode($data, true));

            return;
        }
        $this->dataApi->save(\sha1($url), $data);
    }

    /**
     * @param mixed[] $data
     *
     * @return string[]
     */
    private function auditSecurityHeaders(array &$data, HttpResult $result): array
    {
        $missingHeaders = [];
        foreach (['Strict-Transport-Security', 'Content-Security-Policy', 'X-Frame-Options', 'X-Content-Type-Options', 'Referrer-Policy', 'Permissions-Policy'] as $header) {
            if ($result->getResponse()->hasHeader($header)) {
                continue;
            }
            $data['security'][] = [
                'type' => 'missing-header',
                'value' => $header,
            ];
            $missingHeaders[] = $header;
        }

        return $missingHeaders;
    }

    /**
     * @param mixed[] $data
     */
    private function auditSecurity(string $url, array &$data, HttpResult $result): void
    {
        $missingHeaders = $this->auditSecurityHeaders($data, $result);
        if (\count($missingHeaders) > 0) {
            $this->rapport->addSecurityError($url, \count($missingHeaders));
        }
    }

    /**
     * @param mixed[] $data
     */
    private function auditAccessibility(string $url, array &$data, HttpResult $result): void
    {
        try {
            $pa11y = $this->pa11y($url, $data, $result);
            if (\count($pa11y) > 0) {
                $this->rapport->addAccessibilityError($url, \count($pa11y));
            }
        } catch (\Throwable $e) {
            $this->logger->critical(\sprintf('Pa11y audit for %s failed: %s', $url, $e->getMessage()));
        }
    }

    /**
     * @param mixed[] $data
     *
     * @return mixed[]
     */
    private function pa11y(string $url, array &$data, HttpResult $result): array
    {
        if (!$result->isHtml()) {
            $this->logger->notice(\sprintf('Mimetype %s not supported to audit accessibility', $result->getMimetype()));

            return [];
        }
        $this->pa11yWrapper->run($url);
        $data['pa11y'] = $this->pa11yWrapper->getJson();

        return $data['pa11y'];
    }

    /**
     * @param mixed[] $data
     */
    private function info(string $url, array &$data, HttpResult $result): void
    {
        $data['status_code'] = $result->getResponse()->getStatusCode();
        $data['mimetype'] = $result->getMimetype();
        $data['url'] = $url;
        $urlInfo = new Url($url);
        $data['host'] = $urlInfo->getHost();
        $data['timestamp'] = (new \DateTimeImmutable())->format('c');
    }

    /**
     * @param mixed[] $data
     */
    private function auditLighthouse(string $url, array &$data): void
    {
        try {
            $wrapper = (new LighthouseWrapper())->run($url);
            $lighthouse = $wrapper->getJson();
            if (isset($lighthouse['audits']['final-screenshot']['details']['data']) && \is_string($lighthouse['audits']['final-screenshot']['details']['data'])) {
                $output_array = [];
                \preg_match('/data:(?P<mimetype>[a-z\/\-\+]+\/[a-z\/\-\+]+);base64,(?P<base64>.+)/', $lighthouse['audits']['final-screenshot']['details']['data'], $output_array);
                if (isset($output_array['mimetype']) && isset($output_array['base64']) && \is_string($output_array['mimetype']) && \is_string($output_array['base64'])) {
                    $stream = new StringStream(\base64_decode($output_array['base64']));
                    $hash = $this->fileApi->uploadStream($stream, 'final-screenshot', $output_array['mimetype']);
                    $data['screenshot'] = [
                        'sha1' => $hash,
                        'filename' => 'final-screenshot',
                        'mimetype' => $output_array['mimetype'],
                    ];
                }
            }
            if (\is_array($lighthouse['runWarnings'] ?? null) && \count($lighthouse['runWarnings']) > 0) {
                $data['warning'] = $lighthouse['runWarnings'][0];
            }
            if (\is_array($lighthouse['categories'] ?? null)) {
                foreach ($lighthouse['categories'] as $category) {
                    $data[\sprintf('lighthouse_%s', $category['id'])] = $category['score'] ?? null;
                }
            }
            unset($lighthouse['i18n']);
            unset($lighthouse['timing']);
            unset($lighthouse['audits']['full-page-screenshot']);
            unset($lighthouse['audits']['screenshot-thumbnails']);
            unset($lighthouse['audits']['final-screenshot']);
            $data['lighthouse_report'] = Json::encode($lighthouse, true);
        } catch (\Throwable $e) {
            $this->logger->critical(\sprintf('Lighthouse audit for %s failed: %s', $url, $e->getMessage()));
        }
    }
}
