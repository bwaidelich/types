<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Target;

use Closure;
use Wwwision\Types\Schema\Dynamic\DynamicInstance;

/**
 * A {@see Target} backed by nothing. Names itself from a given string and builds a generic
 * {@see DynamicInstance} container via a factory closure, instead of a real PHP class.
 */
final class DynamicTarget implements Target
{
    /**
     * @param Closure(array<int|string, mixed>): DynamicInstance $factory
     */
    public function __construct(
        public readonly string $name,
        private readonly Closure $factory,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function isInstance(mixed $value): bool
    {
        return $value instanceof DynamicInstance && $value->typeName === $this->name;
    }

    public function construct(array $arguments): mixed
    {
        return ($this->factory)($arguments);
    }
}
