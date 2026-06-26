<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Dynamic;

use JsonSerializable;
use Stringable;
use Wwwision\Types\Schema\Schema;

/**
 * Immutable container a binding-less scalar schema (string/integer/float) instantiates into.
 */
final class DynamicValue implements DynamicInstance, JsonSerializable, Stringable
{
    public function __construct(
        private readonly Schema $schema,
        public readonly string|int|float|bool $value,
    ) {}

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function jsonSerialize(): string|int|float|bool
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
