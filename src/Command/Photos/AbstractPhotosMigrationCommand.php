<?php

namespace App\Command\Photos;

use App\Client\Photos\PhotosLibraryInterface;
use EMS\CommonBundle\Common\Admin\AdminHelper;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractPhotosMigrationCommand extends AbstractCommand
{
    private AdminHelper $adminHelper;
    private ConsoleLogger $logger;

    public function __construct(AdminHelper $adminHelper)
    {
        parent::__construct();
        $this->adminHelper = $adminHelper;
    }

    protected function configure(): void
    {
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->logger = new ConsoleLogger($output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->adminHelper->getCoreApi()->isAuthenticated()) {
            $this->io->error(\sprintf('Not authenticated for %s, run ems:admin:login', $this->adminHelper->getCoreApi()->getBaseUrl()));

            return self::EXECUTE_ERROR;
        }

        $library = $this->getLibrary();
        $progressBar = $this->io->createProgressBar($library->photosCount());
        foreach ($library->getPhotos() as $photo) {
            $progressBar->advance();
        }

        return self::EXECUTE_SUCCESS;
    }

    abstract protected function getLibrary(): PhotosLibraryInterface;
}
