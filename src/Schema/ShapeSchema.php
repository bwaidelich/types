<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Webmozart\Assert\Assert;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\Issues\Issues;

use function array_diff_key;
use function array_key_exists;
use function get_object_vars;
use function is_array;
use function is_iterable;
use function is_object;
use function iterator_to_array;
use function sprintf;

final class ShapeSchema implements Schema
{
    /**
     * @param ReflectionClass<object> $reflectionClass
     * @param array<non-empty-string, Schema> $propertySchemas
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
        if (is_object($value) && $this->reflectionClass->isInstance($value)) {
            return $value;
        }
        $arrayValue = $this->coerce($value);
        $constructor = $this->reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $this->reflectionClass->getName()));
        try {
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
            throw CoerceException::invalidType($value, $this);
        }
        $issues = Issues::empty();
        $result = [];
        foreach ($this->propertySchemas as $propertyName => $propertySchema) {
            if (array_key_exists($propertyName, $array)) {
                try {
                    $result[$propertyName] = $propertySchema->instantiate($array[$propertyName]);
                } catch (CoerceException $e) {
                    $issues = $issues->add($e->issues, $propertyName);
                }
                continue;
            }
            if ($propertySchema instanceof OptionalSchema) {
                continue;
            }
            $issues = $issues->add(CoerceException::required($this, $propertySchema)->issues, $propertyName);
        }
        $unrecognizedKeys = array_diff_key($array, $this->propertySchemas);
        if ($unrecognizedKeys !== []) {
            $issues = $issues->add(CoerceException::unrecognizedKeys($value, $this, array_keys($unrecognizedKeys))->issues);
        }
        if (!$issues->isEmpty()) {
            throw CoerceException::fromIssues($issues, $value, $this);
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
