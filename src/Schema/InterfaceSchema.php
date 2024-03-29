<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use ReflectionClass;
use Webmozart\Assert\Assert;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Parser;

use function is_array;
use function sprintf;

final class InterfaceSchema implements Schema
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
        return 'interface';
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

    /**
     * @return array<Schema>
     */
    public function implementationSchemas(): array
    {
        $implementationSchemas = [];
        foreach (get_declared_classes() as $className) {
            $classInterfaces = class_implements($className, false);
            if (!is_array($classInterfaces) || !in_array($this->reflectionClass->getName(), $classInterfaces, true)) {
                continue;
            }
            $implementationSchemas[] = Parser::getSchema($className);
        }
        return $implementationSchemas;
    }

    public function instantiate(mixed $value): mixed
    {
        if (is_object($value) && $this->reflectionClass->isInstance($value)) {
            return $value;
        }
        if (is_array($value)) {
            $array = $value;
        } elseif (is_iterable($value)) {
            $array = iterator_to_array($value);
        } elseif (is_object($value)) {
            $array = get_object_vars($value);
        } else {
            throw CoerceException::invalidType($value, $this);
        }
        if (!array_key_exists('__type', $array)) {
            throw CoerceException::custom('Missing key "__type"', $value, $this);
        }
        $type = $array['__type'];
        if (!is_string($type)) {
            throw CoerceException::custom(sprintf('Key "__type" has to be a string, got: %s', get_debug_type($type)), $value, $this);
        }
        if (!class_exists($type)) {
            throw CoerceException::custom(sprintf('Key "__type" has to be a valid class name, got: "%s"', $type), $value, $this);
        }
        if (isset($array['__value'])) {
            $array = $array['__value'];
        } else {
            unset($array['__type']);
        }
        if ($array === []) {
            throw CoerceException::custom(sprintf('Missing keys for interface of type %s', $this->getName()), $value, $this);
        }
        try {
            $result = Parser::instantiate($type, $array);
        } catch (CoerceException $e) {
            throw CoerceException::fromIssues($e->issues, $value, $this);
        }
        if (!$this->reflectionClass->isInstance($result)) {
            throw CoerceException::custom(sprintf('The given "__type" of "%s" is not an implementation of %s', $type, $this->getName()), $value, $this);
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
                'description' => $this->overriddenPropertyDescription($propertyName) ?? $propertySchema->getDescription(),
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
