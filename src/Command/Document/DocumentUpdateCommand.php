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

    private string $configFile;
    private string $dataFilePath;

    private int $dataOffset;
    private ?int $dataLength = null;
    private bool $dataSkipFirstRow;

    protected static $defaultName = 'emscli:update:documents';

    private const ARGUMENT_DATA_FILE = 'data-file-path';
    private const ARGUMENT_CONFIG_FILE = 'config-file-path';
    private const OPTION_DATA_OFFSET = 'data-offset';
    private const OPTION_DATA_LENGTH = 'data-length';
    private const OPTION_DATA_SKIP_FIRST_ROW = 'data-skip-first';

    public function __construct(AdminHelper $adminHelper, FileReaderInterface $fileReader)
    {
        parent::__construct();
        $this->adminHelper = $adminHelper;
        $this->fileReader = $fileReader;
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_CONFIG_FILE, InputArgument::REQUIRED, 'Config file')
            ->addArgument(self::ARGUMENT_DATA_FILE, InputArgument::REQUIRED, 'Data file')
            ->addOption(self::OPTION_DATA_OFFSET, null, InputOption::VALUE_REQUIRED, 'Offset data', 0)
            ->addOption(self::OPTION_DATA_LENGTH, null, InputOption::VALUE_REQUIRED, 'Length data to parse')
            ->addOption(self::OPTION_DATA_SKIP_FIRST_ROW, null, InputOption::VALUE_OPTIONAL, 'Skip data header', true)
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->configFile = $this->getArgumentString(self::ARGUMENT_CONFIG_FILE);
        $this->dataFilePath = $this->getArgumentString(self::ARGUMENT_DATA_FILE);

        $this->dataOffset = $this->getOptionInt(self::OPTION_DATA_OFFSET);
        $this->dataLength = $this->getOptionIntNull(self::OPTION_DATA_LENGTH);
        $this->dataSkipFirstRow = $this->getOptionBool(self::OPTION_DATA_SKIP_FIRST_ROW);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMS Client - update documents');
        $coreApi = $this->adminHelper->getCoreApi();

        if (!$coreApi->isAuthenticated()) {
            $this->io->error(\sprintf('Not authenticated for %s, run ems:admin:login', $this->adminHelper->getCoreApi()->getBaseUrl()));

            return self::EXECUTE_ERROR;
        }

        $config = DocumentUpdateConfig::fromFile($this->configFile);

        $this->io->section('Reading data');
        $dataArray = $this->fileReader->getData($this->dataFilePath, $this->dataSkipFirstRow);

        $data = new Data($dataArray);
        $this->io->writeln(\sprintf('Loaded data in memory: %d rows', \count($data)));

        if ($this->dataOffset || $this->dataLength) {
            $data->slice($this->dataOffset, $this->dataLength);
            $this->io->writeln(\sprintf('Sliced data: %d rows (start %d)', \count($data), $this->dataOffset));
        }

        $documentUpdater = new DocumentUpdater($data, $config, $coreApi, $this->io);
        $documentUpdater
            ->executeColumnTransformers()
            ->execute()
        ;

        return self::EXECUTE_SUCCESS;
    }
}
