<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Dynamic;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use Wwwision\Types\Schema\ListSchema;

/**
 * Immutable container a binding-less list schema instantiates into. Iterable and countable; exposes
 * its {@see ListSchema} (and thereby its item type). Items may be real value objects or other
 * dynamic values.
 *
 * @implements IteratorAggregate<int|string, mixed>
 */
final class DynamicList implements DynamicInstance, JsonSerializable, IteratorAggregate, Countable
{
    /**
     * @param array<int|string, mixed> $items
     */
    public function __construct(
        private readonly ListSchema $schema,
        private readonly array $items,
    ) {}

    public function getSchema(): ListSchema
    {
        return $this->schema;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
