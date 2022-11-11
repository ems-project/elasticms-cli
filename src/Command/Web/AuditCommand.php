<?php

declare(strict_types=1);

namespace App\Command\Web;

use App\Client\Audit\Cache;
use App\Client\HttpClient\CacheManager;
use App\Client\HttpClient\HttpResult;
use App\Client\WebToElasticms\Helper\Url;
use App\Commands;
use EMS\CommonBundle\Common\Admin\AdminHelper;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class AuditCommand extends AbstractCommand
{
    protected static $defaultName = Commands::WEB_AUDIT;

    private const ARG_URL = 'url';
    private const OPTION_CONTINUE = 'continue';
    public const OPTION_CACHE_FOLDER = 'cache-folder';
    public const OPTION_MAX_UPDATES = 'max-updates';
    public const OPTION_FORCE = 'force';
    public const OPTION_DRY_RUN = 'dry-run';
    public const OPTION_RAPPORTS_FOLDER = 'rapports-folder';
    private ConsoleLogger $logger;
    private string $jsonPath;
    private string $cacheFolder;
    private bool $force;
    private bool $continue;
    private bool $dryRun;
    private string $rapportsFolder;
    private AdminHelper $adminHelper;
    private int $maxUpdate;
    private Url $baseUrl;
    private Cache $auditCache;

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
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'force update all documents')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'don\'t update elasticms')
            ->addOption(self::OPTION_RAPPORTS_FOLDER, null, InputOption::VALUE_OPTIONAL, 'Path to a folder where rapports stored', \getcwd())
            ->addOption(self::OPTION_CACHE_FOLDER, null, InputOption::VALUE_OPTIONAL, 'Path to a folder where cache will stored', \implode(DIRECTORY_SEPARATOR, [\getcwd(), 'cache']))
            ->addOption(self::OPTION_MAX_UPDATES, null, InputOption::VALUE_OPTIONAL, 'Maximum number of document that can be updated in 1 batch (if the continue option is activated)', 500);
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->logger = new ConsoleLogger($output);
        $this->baseUrl = new Url($this->getArgumentString(self::ARG_URL));
        $this->cacheFolder = $this->getOptionString(self::OPTION_CACHE_FOLDER);
        $this->jsonPath = \sprintf('%s/%s.json', $this->cacheFolder, $this->baseUrl->getHost());
        $this->force = $this->getOptionBool(self::OPTION_FORCE);
        $this->continue = $this->getOptionBool(self::OPTION_CONTINUE);
        $this->dryRun = $this->getOptionBool(self::OPTION_DRY_RUN);
        $this->rapportsFolder = $this->getOptionString(self::OPTION_RAPPORTS_FOLDER);
        $this->maxUpdate = $this->getOptionInt(self::OPTION_MAX_UPDATES);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->adminHelper->getCoreApi()->isAuthenticated()) {
            $this->io->error(\sprintf('Not authenticated for %s, run ems:admin:login', $this->adminHelper->getCoreApi()->getBaseUrl()));

            return self::EXECUTE_ERROR;
        }

        $this->io->section('Load config');
        $cacheManager = new CacheManager($this->cacheFolder);
        $this->auditCache = $this->loadAuditCache($cacheManager);

//        $rapport = new Rapport($cacheManager, $this->rapportsFolder);
//        $updateManager = new UpdateManager($this->adminHelper->getCoreApi(), $configManager, $this->logger, $this->dryRun);
//
//
        if ($this->continue) {
            $this->auditCache->reset();
        }

        $this->io->title(\sprintf('Starting auditing %s', $this->baseUrl->getUrl()));
        $counter = 0;
        $finish = true;
        while ($this->auditCache->hasNext()) {
            $result = $cacheManager->get($this->auditCache->next());
            $this->addMissingInternalLinks($this->auditCache->current(), $result);
            $hash = $this->hashFromResources($result);
//            $updateManager->update($extractedData, $this->force, $rapport);
            $this->auditCache->save($this->jsonPath);
//            $rapport->save();
            if ($this->continue && ++$counter >= $this->maxUpdate) {
                $finish = false;
                break;
            }
            $this->auditCache->progress($output);
        }
        $this->auditCache->progressFinish($output, $counter);

        $this->io->section('Save cache and rapport');
        $this->auditCache->save($this->jsonPath, $finish);
//        $rapport->save();

        return self::EXECUTE_SUCCESS;
    }

    protected function loadAuditCache(CacheManager $cacheManager): Cache
    {
        if (!\file_exists($this->jsonPath)) {
            return new Cache($cacheManager, $this->baseUrl, $this->adminHelper->getCoreApi(), $this->logger);
        }
        $contents = \file_get_contents($this->jsonPath);
        if (false === $contents) {
            throw new \RuntimeException('Unexpected false config file');
        }

        return Cache::deserialize($contents, $cacheManager, $this->adminHelper->getCoreApi(), $this->logger);
    }

    private function addMissingInternalLinks(string $url, HttpResult $result): void
    {
        if (0 !== \strpos($result->getMimetype(), 'text/html')) {
            $this->logger->notice(\sprintf('Mimetype %s not supported to extract internal links', $result->getMimetype()));
        }

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
            $link = new Url($href, $url);
            $this->addMissingInternalLink($link);
        }
    }

    private function addMissingInternalLink(Url $url): void
    {
        if (!\in_array($url->getHost(), $this->auditCache->getHosts())) {
            return;
        }
        if (!\in_array($url->getScheme(), ['http', 'https'])) {
            return;
        }

        $this->auditCache->addUrl($url->getUrl());
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
}
