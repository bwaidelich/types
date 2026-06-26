<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Dynamic;

use JsonSerializable;
use Stringable;

/**
 * Immutable container a binding-less scalar schema (string/integer/float/...) instantiates into.
 */
final class DynamicValue implements DynamicInstance, JsonSerializable, Stringable
{
    public function __construct(
        public readonly string $typeName,
        public readonly string|int|float|bool $value,
    ) {}

    public function jsonSerialize(): string|int|float|bool
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
