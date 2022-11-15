<?php

declare(strict_types=1);

namespace App\Command\Web;

use App\Client\Audit\AuditManager;
use App\Client\Audit\AuditResult;
use App\Client\Audit\Cache;
use App\Client\Audit\Rapport;
use App\Client\HttpClient\CacheManager;
use App\Client\HttpClient\HttpResult;
use App\Client\HttpClient\UrlReport;
use App\Client\WebToElasticms\Helper\Url;
use App\Commands;
use EMS\CommonBundle\Common\Admin\AdminHelper;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\Standard\Json;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class AuditCommand extends AbstractCommand
{
    protected static $defaultName = Commands::WEB_AUDIT;

    private const ARG_URL = 'url';
    private const OPTION_CONTINUE = 'continue';
    private const OPTION_CACHE_FOLDER = 'cache-folder';
    private const OPTION_MAX_UPDATES = 'max-updates';
    private const OPTION_IGNORE_REGEX = 'ignore-regex';
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_PA11Y = 'pa11y';
    private const OPTION_TIKA = 'tika';
    private const OPTION_ALL = 'all';
    private const OPTION_LIGHTHOUSE = 'lighthouse';
    private const OPTION_CONTENT_TYPE = 'content-type';
    private const OPTION_RAPPORTS_FOLDER = 'rapports-folder';
    private ConsoleLogger $logger;
    private string $jsonPath;
    private string $cacheFolder;
    private bool $continue;
    private bool $dryRun;
    private string $rapportsFolder;
    private AdminHelper $adminHelper;
    private int $maxUpdate;
    private Url $baseUrl;
    private Cache $auditCache;
    private string $contentType;
    private CacheManager $cacheManager;
    private bool $lighthouse;
    private bool $pa11y;
    private bool $tika;
    private bool $all;
    private ?string $ignoreRegex;

    public function __construct(AdminHelper $adminHelper)
    {
        parent::__construct();
        $this->adminHelper = $adminHelper;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Audit (security headers, content, locale, accessibility) website')
            ->addArgument(
                self::ARG_URL,
                InputArgument::REQUIRED,
                'Website landing page\'s URL'
            )
            ->addOption(
                self::OPTION_CONTINUE,
                null,
                InputOption::VALUE_NONE,
                'Continue import from last know updated document'
            )
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'don\'t update elasticms')
            ->addOption(self::OPTION_PA11Y, null, InputOption::VALUE_NONE, 'Add a pa11y accessibility audit')
            ->addOption(self::OPTION_LIGHTHOUSE, null, InputOption::VALUE_NONE, 'Add a Lighthouse audit')
            ->addOption(self::OPTION_TIKA, null, InputOption::VALUE_NONE, 'Add a Tika audit')
            ->addOption(self::OPTION_ALL, null, InputOption::VALUE_NONE, 'Add all audits (Tika, pa11y, lighthouse')
            ->addOption(self::OPTION_CONTENT_TYPE, null, InputOption::VALUE_OPTIONAL, 'Audit\'s content type', 'audit')
            ->addOption(self::OPTION_RAPPORTS_FOLDER, null, InputOption::VALUE_OPTIONAL, 'Path to a folder where rapports stored', \getcwd())
            ->addOption(self::OPTION_CACHE_FOLDER, null, InputOption::VALUE_OPTIONAL, 'Path to a folder where cache will stored', \implode(DIRECTORY_SEPARATOR, [\getcwd(), 'cache']))
            ->addOption(self::OPTION_MAX_UPDATES, null, InputOption::VALUE_OPTIONAL, 'Maximum number of document that can be updated in 1 batch (if the continue option is activated)', 500)
            ->addOption(self::OPTION_IGNORE_REGEX, null, InputOption::VALUE_OPTIONAL, 'Regex that will defined paths \'(^\/path_pattern|^\/second_pattern\' to ignore');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->logger = new ConsoleLogger($output);
        $this->baseUrl = new Url($this->getArgumentString(self::ARG_URL));
        $this->cacheFolder = $this->getOptionString(self::OPTION_CACHE_FOLDER);
        $this->jsonPath = \sprintf('%s/%s.json', $this->cacheFolder, $this->baseUrl->getHost());
        $this->continue = $this->getOptionBool(self::OPTION_CONTINUE);
        $this->dryRun = $this->getOptionBool(self::OPTION_DRY_RUN);
        $this->lighthouse = $this->getOptionBool(self::OPTION_LIGHTHOUSE);
        $this->pa11y = $this->getOptionBool(self::OPTION_PA11Y);
        $this->tika = $this->getOptionBool(self::OPTION_TIKA);
        $this->all = $this->getOptionBool(self::OPTION_ALL);
        $this->rapportsFolder = $this->getOptionString(self::OPTION_RAPPORTS_FOLDER);
        $this->contentType = $this->getOptionString(self::OPTION_CONTENT_TYPE);
        $this->maxUpdate = $this->getOptionInt(self::OPTION_MAX_UPDATES);
        $this->ignoreRegex = $this->getOptionStringNull(self::OPTION_IGNORE_REGEX);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->adminHelper->getCoreApi()->isAuthenticated()) {
            $this->io->error(\sprintf('Not authenticated for %s, run ems:admin:login', $this->adminHelper->getCoreApi()->getBaseUrl()));

            return self::EXECUTE_ERROR;
        }

        $this->io->section('Load config');
        $this->cacheManager = new CacheManager($this->cacheFolder, false);
        $this->auditCache = $this->loadAuditCache();
        $api = $this->adminHelper->getCoreApi()->data($this->contentType);

        $rapport = $this->auditCache->getRapport();
        if (null === $rapport || !$this->continue) {
            $rapport = new Rapport();
        }
        $rapport->setFolder($this->rapportsFolder);
        $auditManager = new AuditManager($this->cacheManager, $this->logger, $this->all, $this->pa11y, $this->lighthouse, $this->tika);

        if ($this->continue) {
            $this->auditCache->reset();
        }

        $this->io->title(\sprintf('Starting auditing %s', $this->baseUrl->getUrl()));
        $counter = 0;
        $finish = true;
        while ($this->auditCache->hasNext()) {
            $url = $this->auditCache->next();
            if (null !== $this->ignoreRegex && \preg_match(\sprintf('/%s/', $this->ignoreRegex), $url->getPath())) {
                $rapport->addIgnoredUrl($url, 'Ignored by regex');
                continue;
            }
            $result = $this->cacheManager->get($url->getUrl());
            if (!$result->hasResponse()) {
                $rapport->addBrokenLink(new UrlReport($url, 0, $result->getErrorMessage()));
                continue;
            }
            if (\in_array($result->getResponse()->getStatusCode(), [301, 302, 303, 307, 308])) {
                if (!$result->getResponse()->hasHeader('Location')) {
                    $rapport->addBrokenLink(new UrlReport($url, $result->getResponse()->getStatusCode(), 'Redirect without Location header'));
                    continue;
                }
                $location = $result->getResponse()->getHeader('Location')[0] ?? null;
                if (null === $location) {
                    throw new \RuntimeException('Unexpected missing Location');
                }
                $link = new Url($location, $url->getPath());
                if ($this->auditCache->inHosts($link->getHost())) {
                    $this->auditCache->addUrl($link);
                    $rapport->addWarning($url, [\sprintf('Redirect (%d) to %s', $result->getResponse()->getStatusCode(), $location)]);
                } else {
                    $rapport->addWarning($url, [\sprintf('External redirect (%d) to %s', $result->getResponse()->getStatusCode(), $location)]);
                }
                continue;
            }
            $hash = $this->hashFromResources($result);
            $auditResult = $auditManager->analyze($url, $result, $hash, $rapport);
            if (!$auditResult->isValid()) {
                $rapport->addBrokenLink($auditResult->getUrlReport());
            }
            if (\count($auditResult->getPa11y()) > 0) {
                $rapport->addAccessibilityError($url->getUrl(), \count($auditResult->getPa11y()), $auditResult->getAccessibility());
            }
            if (\count($auditResult->getSecurityWarnings()) > 0) {
                $rapport->addSecurityError($url->getUrl(), \count($auditResult->getSecurityWarnings()), $auditResult->getBestPractices());
            }
            if (\count($auditResult->getWarnings()) > 0) {
                $rapport->addWarning($url, $auditResult->getWarnings());
            }
            $this->treatLinks($auditResult, $rapport);
            if (!$this->dryRun) {
                $assets = $auditResult->uploadAssets($this->adminHelper->getCoreApi()->file());
                $rawData = $auditResult->getRawData($assets);
                $this->logger->notice(Json::encode($rawData, true));
                $api->save($auditResult->getUrl()->getId(), $rawData);
            } else {
                $this->logger->notice(Json::encode($auditResult->getRawData([]), true));
            }
            $this->auditCache->setRapport($rapport);
            $this->auditCache->save($this->jsonPath);
            $rapport->save();
            if (++$counter >= $this->maxUpdate && $this->continue) {
                $finish = false;
                break;
            }
            $this->auditCache->progress($output);
        }
        $this->auditCache->progressFinish($output, $counter);

        $this->io->section('Save cache and rapport');
        $this->auditCache->setRapport($this->auditCache->hasNext() ? $rapport : null);
        $this->auditCache->save($this->jsonPath, $finish);
        $rapport->save();

        return self::EXECUTE_SUCCESS;
    }

    protected function loadAuditCache(): Cache
    {
        if (!\file_exists($this->jsonPath)) {
            return new Cache($this->baseUrl, $this->logger);
        }
        $contents = \file_get_contents($this->jsonPath);
        if (false === $contents) {
            throw new \RuntimeException('Unexpected false config file');
        }
        $cache = Cache::deserialize($contents, $this->logger);
        $cache->addUrl($this->baseUrl);

        return $cache;
    }

    private function hashFromResources(HttpResult $result): string
    {
        $hashContext = \hash_init('sha1');
        $handler = $result->getStream();
        if (0 !== $handler->tell()) {
            $handler->rewind();
        }
        while (!$handler->eof()) {
            \hash_update($hashContext, $handler->read(1024 * 1024));
        }

        return \hash_final($hashContext);
    }

    private function treatLinks(AuditResult $auditResult, Rapport $rapport): void
    {
        foreach ($auditResult->getLinks() as $link) {
            if (!$link->isCrawlable()) {
                $rapport->addIgnoredUrl($link, 'Non-crawlable url');
                continue;
            }

            if ($this->auditCache->inHosts($link->getHost())) {
                $this->auditCache->addUrl($link);
                $auditResult->addInternalLink($link);
            } else {
                try {
                    $urlReport = $this->cacheManager->testUrl($link);
                    if (!$urlReport->isValid()) {
                        $rapport->addBrokenLink($urlReport);
                    }
                    $auditResult->addExternalLink($urlReport);
                } catch (\Throwable $e) {
                    $rapport->addBrokenLink(new UrlReport($link, 0, $e->getMessage()));
                }
            }
        }
    }
}
