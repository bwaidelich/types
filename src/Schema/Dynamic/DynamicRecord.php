<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Dynamic;

use ArrayAccess;
use JsonSerializable;
use LogicException;

use function array_key_exists;
use function sprintf;

/**
 * Immutable container a binding-less shape schema instantiates into. Array-backed with magic read
 * access; its property values may themselves be real value objects (when inherited from an extended
 * class-based schema) or other dynamic values.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class DynamicRecord implements DynamicInstance, JsonSerializable, ArrayAccess
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly string $typeName,
        private readonly array $properties,
    ) {}

    public function __get(string $name): mixed
    {
        if (!array_key_exists($name, $this->properties)) {
            throw new LogicException(sprintf('Property "%s" does not exist on dynamic record "%s"', $name, $this->typeName), 1700000050);
        }
        return $this->properties[$name];
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->properties);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->properties);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('Dynamic records are immutable', 1700000051);
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('Dynamic records are immutable', 1700000052);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->properties;
    }
}
