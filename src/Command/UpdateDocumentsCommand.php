<?php

declare(strict_types=1);

namespace App\Command;

use App\Client\Update\Config\UpdateConfig;
use App\Client\Update\UpdateData;
use EMS\CommonBundle\Common\Admin\AdminHelper;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Contracts\File\FileReaderInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class UpdateDocumentsCommand extends AbstractCommand
{
    private AdminHelper $adminHelper;
    private FileReaderInterface $fileReader;

    private string $dataFilename;
    private UpdateConfig $config;

    protected static $defaultName = 'emscli:update:documents';

    private const ARGUMENT_DATA_FILE = 'data-file';

    public function __construct(AdminHelper $adminHelper, FileReaderInterface $fileReader)
    {
        parent::__construct();
        $this->adminHelper = $adminHelper;
        $this->fileReader = $fileReader;
    }

    protected function configure(): void
    {
        $this
        ->addArgument(
            self::ARGUMENT_DATA_FILE,
            InputArgument::REQUIRED,
            'Data file'
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->dataFilename = $this->getArgumentString(self::ARGUMENT_DATA_FILE);
        $this->config = new UpdateConfig([
            'columns' => [
                [
                    'index' => 0,
                    'type' => 'businessId',
                    'field' => 'code',
                    'contentType' => 'product',
                    'scrollSize' => 2000,
                ],
                [
                    'index' => 4,
                    'type' => 'businessId',
                    'field' => 'code',
                    'contentType' => 'cpv',
                    'scrollSize' => 2000,
                ],
            ],
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMS Client - update documents');
        $coreApi = $this->adminHelper->getCoreApi();

        if (!$coreApi->isAuthenticated()) {
            $this->io->error(\sprintf('Not authenticated for %s, run ems:admin:login', $this->adminHelper->getCoreApi()->getBaseUrl()));

            return self::EXECUTE_ERROR;
        }

        $this->io->section('Reading data');
        $updateData = new UpdateData($this->fileReader->getData($this->dataFilename, true));
        $this->io->note(\sprintf('Loaded data in memory: %d rows', \count($updateData)));

        $this->config->columnTransformers($updateData, $coreApi, $this->io);

        return self::EXECUTE_SUCCESS;
    }
}
