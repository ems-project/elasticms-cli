<?php

declare(strict_types=1);

namespace App\Client\Update;

final class UpdateData implements \Countable
{
    /** @var array<mixed> */
    private array $data;

    /**
     * @param array<mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function count(): int
    {
        return \count($this->data);
    }

    public function searchAndReplace(int $columnIndex, string $search, string $replace): void
    {
        foreach ($this->data as &$row) {
            if (isset($row[$columnIndex]) && $search === $row[$columnIndex]) {
                $row[$columnIndex] = $replace;
            }
        }
    }
}
