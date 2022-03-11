<?php

declare(strict_types=1);

namespace App\Client\Document\Update;

use App\Client\Data\Column\DataColumn;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DocumentUpdateConfig
{
    /** @var DataColumn[] */
    public array $dataColumns;

    public ?int $dataFrom = null;
    public ?int $dataUntil = null;

    public string $updateContentType;
    public int $updateIndexEmsId;
    /** @var UpdateMap[] */
    public array $updateMapping;

    /**
     * @param array<mixed> $config
     */
    public function __construct(array $config)
    {
        $resolver = $this->getOptionsResolver();
        /** @var array{'update': array{'contentType': string, 'indexEmsId': int, 'mapping': UpdateMap[]}, 'dataColumns': DataColumn[]} $config */
        $config = $resolver->resolve($config);

        $this->dataColumns = $config['dataColumns'];

        $this->updateContentType = strval($config['update']['contentType']);
        $this->updateIndexEmsId = intval($config['update']['indexEmsId']);
        $this->updateMapping = $config['update']['mapping'];
    }

    private function getOptionsResolver(): OptionsResolver
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setDefaults([
                'dataColumns' => [],
            ])
            ->setDefault('update', function (OptionsResolver $updateResolver) {
                $updateResolver
                    ->setDefaults(['mapping' => []])
                    ->setRequired(['contentType', 'indexEmsId', 'mapping'])
                    ->setNormalizer('mapping', function (Options $options, array $value) {
                        return \array_map(fn ($map) => new UpdateMap($map), $value);
                    })
                ;
            })
            ->setNormalizer('dataColumns', function (Options $options, array $value) {
                return \array_map(function (array $column) {
                    $class = DataColumn::TYPES[$column['type']] ?? false;

                    if (!$class) {
                        throw new \RuntimeException(\sprintf('Invalid column type "%s", allowed type "%s"', $column['type'], \implode('|', \array_keys(DataColumn::TYPES))));
                    }

                    return new $class($column);
                }, $value);
            })
        ;

        return $optionsResolver;
    }
}
