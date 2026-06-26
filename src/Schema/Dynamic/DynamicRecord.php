<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Dynamic;

use JsonSerializable;
use LogicException;

use function array_key_exists;
use function sprintf;

/**
 * Immutable container a binding-less shape schema instantiates into. Properties are read with the
 * natural object-accessor syntax (`$record->propertyName`) via {@see __get}; for statically analyzed
 * code use the explicit {@see get()} / {@see has()}. Property values may themselves be real value
 * objects (when inherited from an extended class-based schema) or other dynamic values.
 */
final class DynamicRecord implements DynamicInstance, JsonSerializable
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
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    public function get(string $name): mixed
    {
        if (!array_key_exists($name, $this->properties)) {
            throw new LogicException(sprintf('Property "%s" does not exist on dynamic record "%s"', $name, $this->typeName), 1700000050);
        }
        return $this->properties[$name];
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->properties);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->properties;
    }
}
