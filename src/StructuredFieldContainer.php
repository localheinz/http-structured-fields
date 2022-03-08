<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @template TKey
 * @template TValue
 *
 * @extends IteratorAggregate<TKey, TValue>
 */
interface StructuredFieldContainer extends Countable, IteratorAggregate, StructuredField
{
    public function isEmpty(): bool;

    /**
     * @throws InvalidIndex If the key does not exist in the container
     */
    public function getByKey(string $key): Item|InnerList|null;

    public function hasKey(string $key): bool;

    /**
     * @throws InvalidIndex If the index does not exist in the container
     */
    public function getByIndex(int $index): Item|InnerList|null;

    public function hasIndex(int $index): bool;

    /**
     * @return Iterator<TKey, TValue>
     */
    public function getIterator(): Iterator;
}
