<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Target;

use Closure;
use Wwwision\Types\Schema\Dynamic\DynamicInstance;
use Wwwision\Types\Schema\Dynamic\DynamicList;
use Wwwision\Types\Schema\Dynamic\DynamicRecord;
use Wwwision\Types\Schema\Dynamic\DynamicValue;
use Wwwision\Types\Schema\ListSchema;
use Wwwision\Types\Schema\Schema;
use Wwwision\Types\Schema\ShapeSchema;

use function is_array;
use function is_scalar;
use function reset;

/**
 * A {@see Target} backed by nothing. Names itself from a given string and builds a generic
 * {@see DynamicInstance} container (carrying the owning {@see Schema}) instead of a real PHP class.
 */
final class DynamicTarget implements Target
{
    /**
     * @param Closure(Schema, array<int|string, mixed>): DynamicInstance $factory
     */
    public function __construct(
        public readonly string $name,
        private readonly Closure $factory,
    ) {}

    /** Builds {@see DynamicValue} containers (scalar leaf schemas). */
    public static function scalar(string $name): self
    {
        return new self($name, static function (Schema $schema, array $arguments): DynamicValue {
            $value = reset($arguments);
            assert(is_scalar($value));
            return new DynamicValue($schema, $value);
        });
    }

    /** Builds {@see DynamicRecord} containers (shape schemas). */
    public static function record(string $name): self
    {
        return new self($name, static function (Schema $schema, array $arguments): DynamicRecord {
            assert($schema instanceof ShapeSchema);
            $properties = [];
            foreach ($arguments as $key => $value) {
                $properties[(string) $key] = $value;
            }
            return new DynamicRecord($schema, $properties);
        });
    }

    /** Builds {@see DynamicList} containers (list schemas). */
    public static function list(string $name): self
    {
        return new self($name, static function (Schema $schema, array $arguments): DynamicList {
            assert($schema instanceof ListSchema);
            $items = reset($arguments);
            assert(is_array($items));
            return new DynamicList($schema, $items);
        });
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isInstance(mixed $value): bool
    {
        return $value instanceof DynamicInstance && $value->getSchema()->getName() === $this->name;
    }

    public function construct(Schema $schema, array $arguments): mixed
    {
        return ($this->factory)($schema, $arguments);
    }
}
