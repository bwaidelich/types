<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use ReflectionClass;
use Webmozart\Assert\Assert;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\InvalidSchemaException;
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
        public readonly null|string $description,
        public readonly array $propertySchemas,
        private readonly array $overriddenPropertyDescriptions,
        public readonly null|Discriminator $discriminator,
    ) {
        Assert::allIsInstanceOf($this->propertySchemas, Schema::class);
    }

    public function withDiscriminator(Discriminator $discriminator): self
    {
        return new self($this->reflectionClass, $this->description, $this->propertySchemas, $this->overriddenPropertyDescriptions, $discriminator);
    }

    public function getType(): string
    {
        return 'interface';
    }

    public function getName(): string
    {
        return $this->reflectionClass->getShortName();
    }

    public function getDescription(): null|string
    {
        return $this->description;
    }

    public function overriddenPropertyDescription(string $propertyName): null|string
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

    public function isInstance(mixed $value): bool
    {
        return is_object($value) && $this->reflectionClass->isInstance($value);
    }

    public function instantiate(mixed $value): object
    {
        if ($this->isInstance($value)) {
            /** @var object $value */
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
        $discriminatorPropertyName = $this->discriminator?->propertyName ?? '__type';
        if (!array_key_exists($discriminatorPropertyName, $array)) {
            throw CoerceException::custom('Missing discriminator key "' . $discriminatorPropertyName . '"', $value, $this);
        }
        $type = $array[$discriminatorPropertyName];
        if (!is_string($type)) {
            throw CoerceException::custom(sprintf('Discriminator key "%s" has to be a string, got: %s', $discriminatorPropertyName, get_debug_type($type)), $value, $this);
        }
        if ($this->discriminator?->mapping !== null) {
            if (!array_key_exists($type, $this->discriminator->mapping)) {
                throw CoerceException::custom(sprintf('Discriminator key "%s" has to be one of "%s". Got: "%s"', $discriminatorPropertyName, implode('", "', array_keys($this->discriminator->mapping)), $type), $value, $this);
            }
            $type = $this->discriminator->mapping[$type];
            if (!class_exists($type)) {
                throw new InvalidSchemaException(sprintf('Discriminator mapping of type "%s" refers to non-existing class "%s"', $this->getName(), $type), 1734001657);
            }
        } elseif (!class_exists($type)) {
            throw CoerceException::custom(sprintf('Discriminator key "%s" has to be a valid class name, got: "%s"', $discriminatorPropertyName, $type), $value, $this);
        }
        if (property_exists($type, $discriminatorPropertyName)) {
            throw new InvalidSchemaException(sprintf('Discriminator key "%s" of type "%s" is ambiguous with the property "%s" of implementation "%s"', $discriminatorPropertyName, $this->getName(), $discriminatorPropertyName, $type), 1734623970);
        }
        if (isset($array['__value'])) {
            $array = $array['__value'];
        } else {
            unset($array[$discriminatorPropertyName]);
        }
        try {
            $result = Parser::instantiate($type, $array);
        } catch (CoerceException $e) {
            throw CoerceException::fromIssues($e->issues, $value, $this);
        }
        if (!$this->reflectionClass->isInstance($result)) {
            throw CoerceException::custom(sprintf('The given "%s" of "%s" is not an implementation of %s', $discriminatorPropertyName, $type, $this->getName()), $value, $this);
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
