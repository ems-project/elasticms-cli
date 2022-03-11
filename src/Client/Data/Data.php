<?php

declare(strict_types=1);

namespace App\Client\Data;

/**
 * @implements \IteratorAggregate<array<mixed>>
 */
final class Data implements \Countable, \IteratorAggregate
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

    public function slice(?int $start = 0, ?int $until = null): void
    {
        $offset = ($start ??= 0);
        $length = $until - $start;
        $this->data = \array_slice($this->data, $offset, $length);
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
