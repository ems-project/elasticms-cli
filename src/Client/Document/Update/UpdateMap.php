<?php

declare(strict_types=1);

namespace App\Client\Document\Update;

final class UpdateMap
{
    public string $field;
    public int $dataColumnIndex;

    /**
     * @param array{'field': string, 'indexDataColumn': int} $config
     */
    public function __construct(array $config)
    {
        $this->field = $config['field'];
        $this->dataColumnIndex = $config['indexDataColumn'];
    }
}