<?php

declare(strict_types=1);

namespace App\Client\Update\Config;

use App\Client\Update\Config\Column\TransformContext;
use App\Client\Update\Config\Column\UpdateConfigColumn;
use App\Client\Update\UpdateData;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Contracts\CoreApi\CoreApiInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UpdateConfig
{
    /** @var UpdateConfigColumn[] */
    private array $columns;

    /**
     * @param array<mixed> $config
     */
    public function __construct(array $config)
    {
        $resolver = $this->getOptionsResolver();
        $config = $resolver->resolve($config);

        $this->columns = $config['columns'] ?? [];
    }

    public static function fromJson(string $json): self
    {
        return new self(Json::decode($json));
    }

    public function columnTransformers(UpdateData $updateData, CoreApiInterface $coreApi, SymfonyStyle $io): void
    {
        $transformContext = new TransformContext($coreApi, $io);

        foreach ($this->columns as $column) {
            $column->transform($updateData, $transformContext);
        }
    }

    private function getOptionsResolver(): OptionsResolver
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setDefaults([
                'columns' => [],
            ])
            ->setNormalizer('columns', function (Options $options, array $value) {
                return \array_map(function (array $column) {
                    $class = UpdateConfigColumn::TYPES[$column['type']] ?? false;

                    if (!$class) {
                        throw new \RuntimeException(\vsprintf('Invalid column type "%s", allowed type "%s"', [$column['type'], \implode('|', \array_keys(UpdateConfigColumn::TYPES))]));
                    }

                    return new $class($column);
                }, $value);
            })
        ;

        return $optionsResolver;
    }
}
