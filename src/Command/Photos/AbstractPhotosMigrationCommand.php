<?php

namespace App\Command\Photos;

use App\Client\Photos\PhotosLibraryInterface;
use EMS\CommonBundle\Common\Admin\AdminHelper;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractPhotosMigrationCommand extends AbstractCommand
{
    public const OPTION_CONTENT_TYPE_NAME = 'content-type-name';
    private AdminHelper $adminHelper;
    private ConsoleLogger $logger;
    private string $contentTypeName;

    public function __construct(AdminHelper $adminHelper)
    {
        parent::__construct();
        $this->adminHelper = $adminHelper;
    }

    protected function configure(): void
    {
        $this->addOption(self::OPTION_CONTENT_TYPE_NAME, null, InputOption::VALUE_OPTIONAL, 'Content type name in elasticms', 'photo');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->logger = new ConsoleLogger($output);
        $this->contentTypeName = $this->getOptionString(self::OPTION_CONTENT_TYPE_NAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->adminHelper->getCoreApi()->isAuthenticated()) {
            $this->io->error(\sprintf('Not authenticated for %s, run ems:admin:login', $this->adminHelper->getCoreApi()->getBaseUrl()));

            return self::EXECUTE_ERROR;
        }

        $library = $this->getLibrary();
        $dataApi = $this->adminHelper->getCoreApi()->data($this->contentTypeName);
        $progressBar = $this->io->createProgressBar($library->photosCount());
        foreach ($library->getPhotos() as $photo) {
            $dataApi->save($photo->getOuuid(), $photo->getData());
            $progressBar->advance();
        }

        return self::EXECUTE_SUCCESS;
    }

    abstract protected function getLibrary(): PhotosLibraryInterface;
}
