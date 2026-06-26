<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Dynamic;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use Traversable;
use Wwwision\Types\Schema\ShapeSchema;

use function array_key_exists;
use function array_keys;
use function sprintf;

/**
 * Immutable container a binding-less shape schema instantiates into. Properties are read with the
 * natural object-accessor syntax (`$record->propertyName`) via {@see __get}; for statically analyzed
 * code use {@see get()} / {@see has()}. Iterable as `propertyName => value`, and exposes its
 * {@see ShapeSchema} so consumers can introspect property names, types and descriptions.
 *
 * Property values may themselves be real value objects (inherited from an extended class-based
 * schema) or other dynamic values.
 *
 * @implements IteratorAggregate<string, mixed>
 */
final class DynamicRecord implements DynamicInstance, JsonSerializable, IteratorAggregate
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        private readonly ShapeSchema $schema,
        private readonly array $properties,
    ) {}

    public function getSchema(): ShapeSchema
    {
        return $this->schema;
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    public function __set(string $name, mixed $value): never
    {
        throw new LogicException(sprintf('Dynamic record "%s" is immutable', $this->schema->getName()), 1700000053);
    }

    public function __unset(string $name): never
    {
        throw new LogicException(sprintf('Dynamic record "%s" is immutable', $this->schema->getName()), 1700000054);
    }

    public function get(string $name): mixed
    {
        if (!array_key_exists($name, $this->properties)) {
            throw new LogicException(sprintf('Property "%s" does not exist on dynamic record "%s"', $name, $this->schema->getName()), 1700000050);
        }
        return $this->properties[$name];
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->properties);
    }

    /**
     * @return list<string>
     */
    public function propertyNames(): array
    {
        return array_keys($this->properties);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->properties);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->properties;
    }
}
