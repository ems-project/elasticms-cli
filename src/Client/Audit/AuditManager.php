<?php

namespace App\Client\Audit;

use App\Client\HttpClient\HttpResult;
use EMS\CommonBundle\Contracts\CoreApi\Endpoint\Data\DataInterface;
use Psr\Log\LoggerInterface;

class AuditManager
{
    private DataInterface $dataApi;
    private Rapport $rapport;
    private LoggerInterface $logger;
    private bool $dryRun;
    private bool $force;

    public function __construct(DataInterface $dataApi, Rapport $rapport, LoggerInterface $logger, bool $dryRun, bool $force)
    {
        $this->dataApi = $dataApi;
        $this->rapport = $rapport;
        $this->logger = $logger;
        $this->dryRun = $dryRun;
        $this->force = $force;
    }

    public function analyze(string $url, HttpResult $result, string $hash): void
    {
        $data = [];
        $this->auditSecurity($url, $data, $result);
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
            if (isset($result->getResponse()->getHeaders()[$header])) {
                continue;
            }
            $data['security'][] = [
                'type' => 'missing-header',
                'value' => '$header',
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
}
