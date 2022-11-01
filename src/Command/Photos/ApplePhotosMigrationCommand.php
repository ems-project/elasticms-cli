<?php

namespace App\Command\Photos;

use App\Commands;
use EMS\CommonBundle\Common\Admin\AdminHelper;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class ApplePhotosMigrationCommand extends AbstractCommand
{
    protected static $defaultName = Commands::APPLE_PHOTOS_MIGRATION;

    public const ARG_PHOTOS_LIBRARY_PATH = 'PHOTOS_LIBRARY_PATH';
    private AdminHelper $adminHelper;
    private ConsoleLogger $logger;
    private string $applePhotosPathPath;

    public function __construct(AdminHelper $adminHelper)
    {
        parent::__construct();
        $this->adminHelper = $adminHelper;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Migrate Apple Photo library to elaticms documents')
            ->addArgument(
                self::ARG_PHOTOS_LIBRARY_PATH,
                InputArgument::REQUIRED,
                'Path to a Apple Photos library'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->logger = new ConsoleLogger($output);
        $this->applePhotosPathPath = $this->getArgumentString(self::ARG_PHOTOS_LIBRARY_PATH);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->adminHelper->getCoreApi()->isAuthenticated()) {
            $this->io->error(\sprintf('Not authenticated for %s, run ems:admin:login', $this->adminHelper->getCoreApi()->getBaseUrl()));

            return self::EXECUTE_ERROR;
        }
        $this->io->title(\sprintf('Start migrating Apple Photos Library %s', $this->applePhotosPathPath));

        return self::EXECUTE_SUCCESS;
    }
}
