<?php

namespace App\Client\Audit;

use App\Client\HttpClient\HttpResult;
use App\Client\HttpClient\UrlReport;
use App\Client\WebToElasticms\Helper\Url;
use App\Helper\Pa11yWrapper;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Contracts\CoreApi\Endpoint\Data\DataInterface;
use Psr\Log\LoggerInterface;

class AuditManager
{
    private DataInterface $dataApi;
    private Rapport $rapport;
    private LoggerInterface $logger;
    private bool $dryRun;
    private bool $force;
    private Pa11yWrapper $pa11yWrapper;

    public function __construct(DataInterface $dataApi, Rapport $rapport, LoggerInterface $logger, bool $dryRun, bool $force)
    {
        $this->dataApi = $dataApi;
        $this->rapport = $rapport;
        $this->logger = $logger;
        $this->dryRun = $dryRun;
        $this->force = $force;
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
        $this->auditAccessibility($url, $data, $result);
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
        $pa11y = $this->pa11y($url, $data, $result);
        if (\count($pa11y) > 0) {
            $this->rapport->addAccessibilityError($url, \count($pa11y));
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
    }
}
