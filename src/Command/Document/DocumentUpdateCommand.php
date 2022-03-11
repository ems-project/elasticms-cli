<?php

declare(strict_types=1);

namespace App\Command\Document;

use App\Client\Data\Data;
use App\Client\Document\Update\DocumentUpdateConfig;
use App\Client\Document\Update\DocumentUpdater;
use EMS\CommonBundle\Common\Admin\AdminHelper;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Contracts\File\FileReaderInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DocumentUpdateCommand extends AbstractCommand
{
    private AdminHelper $adminHelper;
    private FileReaderInterface $fileReader;

    private string $dataFilename;
    private DocumentUpdateConfig $config;

    protected static $defaultName = 'emscli:update:documents';

    private const ARGUMENT_DATA_FILE = 'data-file';
    private const OPTION_DATA_FROM = 'data-from';
    private const OPTION_DATA_UNTIL = 'data-until';

    public function __construct(AdminHelper $adminHelper, FileReaderInterface $fileReader)
    {
        parent::__construct();
        $this->adminHelper = $adminHelper;
        $this->fileReader = $fileReader;
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_DATA_FILE, InputArgument::REQUIRED, 'Data file')
            ->addOption(self::OPTION_DATA_FROM, null,InputOption::VALUE_REQUIRED, 'Start row in data')
            ->addOption(self::OPTION_DATA_UNTIL, null,InputOption::VALUE_REQUIRED, 'End row in data')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->dataFilename = $this->getArgumentString(self::ARGUMENT_DATA_FILE);
        $this->config = new DocumentUpdateConfig([
            'update' => [
                'contentType' => 'product',
                'indexEmsId' => 0,
                'mapping' => [
                    ['field' => 'cpv', 'indexDataColumn' => 4]
                ]
            ],
            'dataColumns' => [
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

        $this->config->dataFrom = $this->getOptionIntNull(self::OPTION_DATA_FROM);
        $this->config->dataUntil = $this->getOptionIntNull(self::OPTION_DATA_UNTIL);
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
        $data = new Data($this->fileReader->getData($this->dataFilename, true));
        $this->io->writeln(\sprintf('Loaded data in memory: %d rows', \count($data)));

        if ($this->config->dataFrom || $this->config->dataUntil) {
            $data->slice($this->config->dataFrom, $this->config->dataUntil);
            $this->io->writeln(\sprintf('Sliced data: %d rows (from %d - until %d)', \count($data), $this->config->dataFrom, $this->config->dataUntil));
        }



        $documentUpdater = new DocumentUpdater($data, $this->config, $coreApi, $this->io);
        $documentUpdater
            ->executeColumnTransformers()
            ->execute();


        return self::EXECUTE_SUCCESS;
    }
}
