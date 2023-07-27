<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Webmozart\Assert\Assert;

use function array_diff_key;
use function array_key_exists;
use function array_keys;
use function get_debug_type;
use function get_object_vars;
use function implode;
use function is_array;
use function is_iterable;
use function is_object;
use function iterator_to_array;
use function sprintf;

/**
 * @template T of object
 * @implements Schema<T>
 */
final class ShapeSchema implements Schema
{
    /**
     * @param ReflectionClass<T> $reflectionClass
     * @param array<non-empty-string, Schema<bool|int|object|string|null>> $propertySchemas
     * @param array<non-empty-string, string> $overriddenPropertyDescriptions
     */
    public function __construct(
        private readonly ReflectionClass $reflectionClass,
        public readonly ?string $description,
        public readonly array $propertySchemas,
        private readonly array $overriddenPropertyDescriptions,
    ) {
        Assert::allIsInstanceOf($this->propertySchemas, Schema::class);
    }

    public function getType(): string
    {
        return 'object';
    }

    public function getName(): string
    {
        return $this->reflectionClass->getShortName();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function overriddenPropertyDescription(string $propertyName): ?string
    {
        return $this->overriddenPropertyDescriptions[$propertyName] ?? null;
    }

    public function instantiate(mixed $value): mixed
    {
        $arrayValue = $this->coerce($value);
        $constructor = $this->reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $this->reflectionClass->getName()));
        try {
            /** @var T $instance */
            $instance = $this->reflectionClass->newInstanceWithoutConstructor();
            $constructor->invoke($instance, ...$arrayValue);
        // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            throw new RuntimeException(sprintf('Failed to instantiate "%s": %s', $this->getName(), $e->getMessage()), 1688570532, $e);
        }
        // @codeCoverageIgnoreEnd
        return $instance;
    }

    /**
     * @return array<mixed>
     */
    private function coerce(mixed $value): array
    {
        if (is_array($value)) {
            $array = $value;
        } elseif (is_iterable($value)) {
            $array = iterator_to_array($value);
        } elseif (is_object($value)) {
            $array = get_object_vars($value);
        } else {
            throw new InvalidArgumentException(sprintf('Non-iterable value of type %s cannot be casted to instance of %s', get_debug_type($value), $this->getName()));
        }
        $unknownProperties = array_diff_key($array, $this->propertySchemas);
        if ($unknownProperties !== []) {
            throw new InvalidArgumentException(sprintf('Unknown propert%s "%s"', count($unknownProperties) !== 1 ? 'ies' : 'y', implode('", "', array_keys($unknownProperties))));
        }

        $result = [];
        foreach ($this->propertySchemas as $propertyName => $propertySchema) {
            if (array_key_exists($propertyName, $array)) {
                try {
                    $result[$propertyName] = $propertySchema->instantiate($array[$propertyName]);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException(sprintf('At property "%s": %s', $propertyName, $e->getMessage()), 1688564876, $e);
                }
                continue;
            }
            if ($propertySchema instanceof OptionalSchema) {
                continue;
            }
            throw new InvalidArgumentException(sprintf('Missing property "%s"', $propertyName));
        }
        return $result;
    }

    public function jsonSerialize(): array
    {
        $propertyRefs = [];
        foreach ($this->propertySchemas as $propertyName => $propertySchema) {
            $propertyRef = [
                'type' => $propertySchema->getName(),
                'name' => $propertyName,
                'description' => $propertySchema->getDescription(),
            ];
            if ($propertySchema instanceof OptionalSchema) {
                $propertyRef['optional'] = true;
            }
            $propertyRefs[] = $propertyRef;
        }
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'properties' => $propertyRefs,
        ];
    }
}
