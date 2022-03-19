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
    private bool $dryRun;
    private ?string $collectionField;

    protected static $defaultName = 'emscli:documents:update';

    private const ARGUMENT_DATA_FILE = 'data-file';
    private const ARGUMENT_CONFIG_FILE = 'config-file';
    private const OPTION_DATA_OFFSET = 'data-offset';
    private const OPTION_DATA_LENGTH = 'data-length';
    private const OPTION_DATA_SKIP_FIRST_ROW = 'data-skip-first';
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_COLLECTION_FIELD = 'collection-field';

    public function __construct(AdminHelper $adminHelper, FileReaderInterface $fileReader)
    {
        parent::__construct();
        $this->adminHelper = $adminHelper;
        $this->fileReader = $fileReader;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Update documents form excel or csv with custom configuration')
            ->addArgument(self::ARGUMENT_CONFIG_FILE, InputArgument::REQUIRED, 'Config file (json)')
            ->addArgument(self::ARGUMENT_DATA_FILE, InputArgument::REQUIRED, 'Data file (excel or csv)')
            ->addOption(self::OPTION_DATA_OFFSET, null, InputOption::VALUE_REQUIRED, 'Offset data', 0)
            ->addOption(self::OPTION_DATA_LENGTH, null, InputOption::VALUE_REQUIRED, 'Length data to parse')
            ->addOption(self::OPTION_DATA_SKIP_FIRST_ROW, null, InputOption::VALUE_OPTIONAL, 'Skip data header', true)
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Just do a dry run')
            ->addOption(self::OPTION_COLLECTION_FIELD, null, InputOption::VALUE_REQUIRED, 'Data, for a same ouuid, are saved as collection in the given field')
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
        $this->dryRun = $this->getOptionBool(self::OPTION_DRY_RUN);
        $this->collectionField = $this->getOptionStringNull(self::OPTION_COLLECTION_FIELD);
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

        $documentUpdater = new DocumentUpdater($data, $config, $coreApi, $this->io, $this->dryRun, $this->collectionField);
        $documentUpdater
            ->executeColumnTransformers()
            ->execute()
        ;

        return self::EXECUTE_SUCCESS;
    }
}
