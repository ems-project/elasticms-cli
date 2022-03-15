<?php

declare(strict_types=1);

namespace App\Client\Data;

use App\Client\Data\Column\DataColumn;

/**
 * @implements \IteratorAggregate<array<mixed>>
 */
class Data implements \Countable, \IteratorAggregate
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

    public function slice(?int $offset, ?int $length = null): void
    {
        $this->data = \array_slice($this->data, $offset ?? 0, $length);
    }

    /**
     * @return \ArrayIterator<int, array<mixed>>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
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
