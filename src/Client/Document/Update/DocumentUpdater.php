<?php

declare(strict_types=1);

namespace App\Client\Document\Update;

use App\Client\Data\Column\TransformContext;
use App\Client\Data\Data;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Common\Standard\Type;
use EMS\CommonBundle\Contracts\CoreApi\CoreApiInterface;
use EMS\CommonBundle\Contracts\CoreApi\Endpoint\Data\DataInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DocumentUpdater
{
    private Data $data;
    private DocumentUpdateConfig $config;
    private CoreApiInterface $coreApi;
    private SymfonyStyle $io;

    public function __construct(Data $data, DocumentUpdateConfig $config, CoreApiInterface $coreApi, SymfonyStyle $io)
    {
        $this->data = $data;
        $this->config = $config;
        $this->coreApi = $coreApi;
        $this->io = $io;
    }

    public function executeColumnTransformers(): self
    {
        $this->io->section('Executing data column transformers');

        $transformContext = new TransformContext($this->coreApi, $this->io);

        foreach ($this->config->dataColumns as $dataColumn) {
            $dataColumn->transform($this->data, $transformContext);
        }

        return $this;
    }

    public function execute(bool $dryRun): self
    {
        $this->io->section('Executing update');

        $dataApi = $this->coreApi->data($this->config->updateContentType);
        $dataProgress = $this->io->createProgressBar(\count($this->data));

        foreach ($this->data as $i => $row) {
            try {
                $ouuid = $this->getOuuidFromRow($row);
                $rawData = $this->getRawDataFromRow($row);
                if ($this->io->isVerbose()) {
                    $this->io->note(sprintf('Update document %s', $ouuid));
                    $this->io->note(Json::encode($rawData, true));
                }
                if (!$dryRun) {
                    $dataApi->save($ouuid, $rawData, DataInterface::MODE_UPDATE, false);
                }
            } catch (\Throwable $e) {
                $this->io->error(\sprintf('Error in row %d with ouuid %s', $i, ($ouuid ?? '??')));
                if ($this->io->isDebug()) {
                    $this->io->error($e->getMessage());
                }
            }

            $dataProgress->advance();
        }

        return $this;
    }

    /**
     * @param array<mixed> $row
     */
    private function getOuuidFromRow(array $row): string
    {
        $updateIndexEmsId = $this->config->updateIndexEmsId;

        $emsId = $row[$updateIndexEmsId] ?? null;

        if (null === $emsId) {
            throw new \RuntimeException(\sprintf('Row does not contain emsId in column [%d]', $updateIndexEmsId));
        }

        $emsId = Type::string($emsId);

        return EMSLink::fromText($emsId)->getOuuid();
    }

    /**
     * @param array<mixed> $row
     *
     * @return array<mixed>
     */
    private function getRawDataFromRow(array $row): array
    {
        $updateMapping = $this->config->updateMapping;

        $rawData = [];
        foreach ($updateMapping as $updateMap) {
            $updateValue = $row[$updateMap->indexDataColumn] ?? null;

            if (null === $updateValue) {
                throw new \RuntimeException('Row does not contain update value in column [%d]', $updateMap->indexDataColumn);
            }

            $rawData[$updateMap->field] = $updateValue;
        }

        if (0 === \count($rawData)) {
            throw new \RuntimeException('No update found!');
        }

        return $rawData;
    }
}
