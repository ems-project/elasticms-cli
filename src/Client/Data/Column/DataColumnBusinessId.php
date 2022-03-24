<?php

declare(strict_types=1);

namespace App\Client\Data\Column;

use App\Client\Data\Data;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use EMS\CommonBundle\Common\CoreApi\Search\Scroll;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Contracts\CoreApi\CoreApiInterface;
use EMS\CommonBundle\Search\Search;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DataColumnBusinessId extends DataColumn
{
    public string $field;
    public string $contentType;
    public int $scrollSize;
    /** @var ?array<mixed> */
    private ?array $scrollMust;
    private bool $removeNotFound;

    /**
     * @param array<mixed> $config
     */
    public function __construct(array $config)
    {
        /** @var array{index: int, field: string, contentType: string, scrollSize: int, scrollMust: ?array<mixed>, removeNotFound: bool} $options */
        $options = $this->getOptionsResolver()->resolve($config);

        parent::__construct($options['index']);
        $this->field = $options['field'];
        $this->contentType = $options['contentType'];
        $this->scrollSize = $options['scrollSize'];
        $this->removeNotFound = $options['removeNotFound'];
        $this->scrollMust = $options['scrollMust'];
    }

    protected function getOptionsResolver(): OptionsResolver
    {
        $optionsResolver = parent::getOptionsResolver();
        $optionsResolver
            ->setRequired(['field', 'contentType'])
            ->setDefaults([
                'scrollSize' => 1000,
                'scrollMust' => null,
                'removeNotFound' => false,
            ])
            ->setAllowedTypes('removeNotFound', ['bool'])
        ;

        return $optionsResolver;
    }

    public function transform(Data $data, TransformContext $transformContext): void
    {
        parent::transform($data, $transformContext);

        $io = $transformContext->io;
        $io->writeln(\vsprintf('Transforming businessId “%s” for column index %d', [
            $this->contentType,
            $this->columnIndex,
        ]));

        $progressScroll = $io->createProgressBar();
        $scroll = $this->createScroll($transformContext->coreApi);

        foreach ($scroll as $result) {
            if ($businessId = $result->getEMSSource()->get($this->field)) {
                $data->searchAndReplace($this->columnIndex, $businessId, $result->getEmsId());
            }
            $progressScroll->advance();
        }

        $progressScroll->finish();

        if ($this->removeNotFound) {
            $data->filter(fn (array $row) => EMSLink::fromText($row[$this->columnIndex])->isValid());
        }

        $io->newLine(2);
    }

    private function createScroll(CoreApiInterface $coreApi): Scroll
    {
        $environmentAlias = $coreApi->meta()->getDefaultContentTypeEnvironmentAlias($this->contentType);

        $boolQuery = new BoolQuery();
        $boolQuery->addMust(new Exists($this->field));
        if (null !== $this->scrollMust) {
            $boolQuery->addMust($this->scrollMust);
        }

        $search = new Search([$environmentAlias], $boolQuery);
        $search->setContentTypes([$this->contentType]);
        $search->setSources([$this->field]);

        return $coreApi->search()->scroll($search, $this->scrollSize);
    }
}
