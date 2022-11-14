<?php

declare(strict_types=1);

namespace App\Client\Audit;

use App\Client\HttpClient\CacheManager;
use App\Client\HttpClient\HttpResult;
use App\Client\WebToElasticms\Helper\Url;
use App\Helper\LighthouseWrapper;
use App\Helper\Pa11yWrapper;
use App\Helper\TikaWrapper;
use EMS\CommonBundle\Common\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

class AuditManager
{
    private LoggerInterface $logger;
    private bool $lighthouse;
    private bool $pa11y;
    private bool $tika;
    private CacheManager $cacheManager;
    private bool $all;
    private Pa11yWrapper $pa11yAudit;
    private LighthouseWrapper $lighthouseAudit;
    private TikaWrapper $tikaLocaleAudit;
    private TikaWrapper $tikaTextAudit;
    private TikaWrapper $tikaLinksAudit;

    public function __construct(CacheManager $cacheManager, LoggerInterface $logger, bool $all, bool $pa11y, bool $lighthouse, bool $tika)
    {
        $this->cacheManager = $cacheManager;
        $this->logger = $logger;
        $this->pa11y = $pa11y;
        $this->lighthouse = $lighthouse;
        $this->tika = $tika;
        $this->all = $all;
    }

    public function analyze(Url $url, HttpResult $result, string $hash): AuditResult
    {
        $audit = new AuditResult($url, $hash);
        $this->addRequestAudit($audit, $result);
        if (!$result->isValid()) {
            return $audit;
        }
        $this->addCrawlerAudit($audit, $result);
        if ($this->all || $this->pa11y) {
            $this->startPa11yAudit($audit, $result);
        }
        if ($this->all || $this->lighthouse) {
            $this->startLighthouseAudit($audit);
        }
        if ($this->all || $this->tika) {
            $this->startTikaAudits($audit, $result);
        }

        if ($this->all || $this->pa11y) {
            $this->addPa11yAudit($audit, $result);
        }
        if ($this->all || $this->lighthouse) {
            $this->addLighthouseAudit($audit);
        }
        if ($this->all || $this->tika) {
            $this->addTikaAudits($audit, $result);
        }

        return $audit;
    }

    private function addRequestAudit(AuditResult $audit, HttpResult $result): void
    {
        $audit->setErrorMessage($result->getErrorMessage());
        if (!$result->hasResponse()) {
            $audit->setValid(false);

            return;
        }
        $audit->setStatusCode($result->getResponse()->getStatusCode());
        $audit->setMimetype($result->getMimetype());

        foreach (['Strict-Transport-Security', 'Content-Security-Policy', 'X-Frame-Options', 'X-Content-Type-Options', 'Referrer-Policy', 'Permissions-Policy'] as $header) {
            if ($result->getResponse()->hasHeader($header)) {
                continue;
            }
            $audit->addSecurityWaring('missing-header', $header);
        }
    }

    private function startPa11yAudit(AuditResult $audit, HttpResult $result): void
    {
        if (!$result->isHtml()) {
            $this->logger->notice(\sprintf('Mimetype %s not supported to audit accessibility', $result->getMimetype()));

            return;
        }

        try {
            $this->pa11yAudit = new Pa11yWrapper($audit->getUrl()->getUrl());
            $this->pa11yAudit->start();
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf('Pa11y audit for %s failed: %s', $audit->getUrl()->getUrl(), $e->getMessage()));
        }
    }

    private function addPa11yAudit(AuditResult $audit, HttpResult $result): void
    {
        if (!$result->isHtml()) {
            $this->logger->notice(\sprintf('Mimetype %s not supported to audit accessibility', $result->getMimetype()));

            return;
        }

        try {
            $audit->setPa11y($this->pa11yAudit->getJson());
        } catch (\Throwable $e) {
            $this->logger->warning(\sprintf('Pa11y audit for %s failed: %s', $audit->getUrl()->getUrl(), $e->getMessage()));
        }
    }

    private function startLighthouseAudit(AuditResult $audit): void
    {
        try {
            $this->lighthouseAudit = new LighthouseWrapper($audit->getUrl()->getUrl());
            $this->lighthouseAudit->start();
        } catch (\Throwable $e) {
            $this->logger->critical(\sprintf('Lighthouse audit for %s failed: %s', $audit->getUrl()->getUrl(), $e->getMessage()));
        }
    }

    private function addLighthouseAudit(AuditResult $audit): void
    {
        try {
            $lighthouse = $this->lighthouseAudit->getJson();
            if (isset($lighthouse['audits']['final-screenshot']['details']['data']) && \is_string($lighthouse['audits']['final-screenshot']['details']['data'])) {
                $audit->setLighthouseScreenshot($lighthouse['audits']['final-screenshot']['details']['data']);
            }
            if (\is_array($lighthouse['runWarnings'] ?? null)) {
                foreach ($lighthouse['runWarnings'] as $warning) {
                    $audit->addWarning(\strval($warning));
                }
            }
            if (\is_float($lighthouse['categories']['performance']['score'])) {
                $audit->setPerformance($lighthouse['categories']['performance']['score']);
            }
            if (\is_float($lighthouse['categories']['accessibility']['score'])) {
                $audit->setAccessibility($lighthouse['categories']['accessibility']['score']);
            }
            if (\is_float($lighthouse['categories']['best-practices']['score'])) {
                $audit->setBestPractices($lighthouse['categories']['best-practices']['score']);
            }
            if (\is_float($lighthouse['categories']['seo']['score'])) {
                $audit->setSeo($lighthouse['categories']['seo']['score']);
            }
            unset($lighthouse['i18n']);
            unset($lighthouse['timing']);
            unset($lighthouse['audits']['full-page-screenshot']);
            unset($lighthouse['audits']['screenshot-thumbnails']);
            unset($lighthouse['audits']['final-screenshot']);
            $audit->setLighthouseReport(Json::encode($lighthouse, true));
        } catch (\Throwable $e) {
            $this->logger->critical(\sprintf('Lighthouse audit for %s failed: %s', $audit->getUrl()->getUrl(), $e->getMessage()));
        }
    }

    private function startTikaAudits(AuditResult $audit, HttpResult $result): void
    {
        try {
            $stream = $result->getStream();
            $this->tikaLocaleAudit = TikaWrapper::getLocale($stream, $this->cacheManager->getCacheFolder());
            $this->tikaTextAudit = TikaWrapper::getText($stream, $this->cacheManager->getCacheFolder());
            $this->tikaLinksAudit = TikaWrapper::getHtml($stream, $this->cacheManager->getCacheFolder());
            $this->tikaLocaleAudit->start();
            $this->tikaTextAudit->start();
            $this->tikaLinksAudit->start();
        } catch (\Throwable $e) {
            $this->logger->critical(\sprintf('Tika audit for %s failed: %s', $audit->getUrl()->getUrl(), $e->getMessage()));
        }
    }

    private function addTikaAudits(AuditResult $audit, HttpResult $result): void
    {
        try {
            $audit->setLocale($this->tikaLocaleAudit->getOutput());
            $audit->setContent($this->tikaTextAudit->getOutput());
            $audit->setTikaDatetime();
            if ($result->isHtml()) {
                return;
            }
            foreach ($this->tikaLinksAudit->getLinks() as $link) {
                $audit->addLinks(new Url($link, $audit->getUrl()->getUrl()));
            }
        } catch (\Throwable $e) {
            $this->logger->critical(\sprintf('Tika audit for %s failed: %s', $audit->getUrl()->getUrl(), $e->getMessage()));
        }
    }

    private function addCrawlerAudit(AuditResult $audit, HttpResult $result): void
    {
        if (!$result->isHtml()) {
            $this->logger->notice(\sprintf('Mimetype %s not supported by the Crawler', $result->getMimetype()));

            return;
        }

        try {
            $stream = $result->getResponse()->getBody();
            $stream->rewind();
            $crawler = new Crawler($stream->getContents());
            $content = $crawler->filter('a');
            for ($i = 0; $i < $content->count(); ++$i) {
                $item = $content->eq($i);
                $href = $item->attr('href');
                if (null === $href || 0 === \strlen($href) || '#' === \substr($href, 0, 1)) {
                    continue;
                }
                $audit->addLinks(new Url($href, $audit->getUrl()->getUrl()));
            }
        } catch (\Throwable $e) {
            $this->logger->critical(\sprintf('Crawler audit for %s failed: %s', $audit->getUrl()->getUrl(), $e->getMessage()));
        }
    }
}
