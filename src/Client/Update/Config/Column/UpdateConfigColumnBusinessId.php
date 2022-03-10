<?php

declare(strict_types=1);

namespace App\Client\Update\Config\Column;

use App\Client\Update\UpdateData;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use EMS\CommonBundle\Common\CoreApi\Search\Scroll;
use EMS\CommonBundle\Contracts\CoreApi\CoreApiInterface;
use EMS\CommonBundle\Search\Search;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UpdateConfigColumnBusinessId extends UpdateConfigColumn
{
    public string $field;
    public string $contentType;
    public int $scrollSize;

    /**
     * @param array<mixed> $config
     */
    public function __construct(array $config)
    {
        /** @var array{index: int, field: string, contentType: string, scrollSize: int} $options */
        $options = $this->getOptionsResolver()->resolve($config);

        parent::__construct($options['index']);
        $this->field = $options['field'];
        $this->contentType = $options['contentType'];
        $this->scrollSize = $options['scrollSize'];
    }

    protected function getOptionsResolver(): OptionsResolver
    {
        $optionsResolver = parent::getOptionsResolver();
        $optionsResolver
            ->setRequired(['field', 'contentType'])
            ->setDefaults(['scrollSize' => 1000]);

        return $optionsResolver;
    }

    public function transform(UpdateData $updateData, TransformContext $transformContext): void
    {
        $io = $transformContext->io;
        $io->section(\vsprintf('Transforming businessId for %s (column index: %d)', [
            $this->contentType,
            $this->columnIndex,
        ]));

        $progressScroll = $io->createProgressBar();
        $scroll = $this->createScroll($transformContext->coreApi);

        foreach ($scroll as $result) {
            if ($businessId = $result->getEMSSource()->get($this->field)) {
                $updateData->searchAndReplace($this->columnIndex, $businessId, $result->getEmsId());
            }
            $progressScroll->advance();
        }

        $progressScroll->finish();
        $io->newLine(2);
    }

    private function createScroll(CoreApiInterface $coreApi): Scroll
    {
        $environmentAlias = $coreApi->meta()->getDefaultContentTypeEnvironmentAlias($this->contentType);

        $boolQuery = new BoolQuery();
        $boolQuery->addMust(new Exists($this->field));

        $search = new Search([$environmentAlias], $boolQuery);
        $search->setContentTypes([$this->contentType]);
        $search->setSources([$this->field]);

        return $coreApi->search()->scroll($search, $this->scrollSize);
    }
}
