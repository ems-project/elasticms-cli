<?php

declare(strict_types=1);

namespace App\Client\Update\Config\Column;

use App\Client\Update\UpdateData;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class UpdateConfigColumn
{
    public int $columnIndex;

    public const TYPES = [
        'businessId' => UpdateConfigColumnBusinessId::class,
    ];

    public function __construct(int $index)
    {
        $this->columnIndex = $index;
    }

    public function transform(UpdateData $updateData, TransformContext $transformContext): void
    {
    }

    protected function getOptionsResolver(): OptionsResolver
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setRequired(['index', 'type'])
            ->setAllowedValues('type', \array_keys(self::TYPES))
        ;

        return $optionsResolver;
    }
}
