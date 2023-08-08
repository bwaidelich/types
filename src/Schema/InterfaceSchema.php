<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use InvalidArgumentException;
use ReflectionClass;
use Webmozart\Assert\Assert;
use Wwwision\Types\Parser;

use function array_key_exists;
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
            Assert::keyExists($value, '__type', 'The given array has to "__type" key');
            $type = $value['__type'];
            Assert::string($type, 'Expected "__type" to be of type string, got: %s');
            Assert::classExists($type, 'Expected "__type" to be a valid class name, got: %s');
            if (isset($value['__value'])) {
                $value = $value['__value'];
            } else {
                unset($value['__type']);
            }
            $result = Parser::instantiate($type, $value);
            if (!$this->reflectionClass->isInstance($result)) {
                throw new InvalidArgumentException(sprintf('The given "__type" of "%s" is not an implementation of %s', $type, $this->getName()));
            }
            return $result;
        }
        throw new InvalidArgumentException(sprintf('Value of type %s cannot be casted to instance of %s', get_debug_type($value), $this->getName()));
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
