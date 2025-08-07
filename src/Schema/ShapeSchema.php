<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Webmozart\Assert\Assert;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\InvalidSchemaException;
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
     * @param array<non-empty-string, Discriminator> $propertyDiscriminators
     */
    public function __construct(
        private readonly ReflectionClass $reflectionClass,
        public readonly null|string $description,
        public readonly array $propertySchemas,
        private readonly array $overriddenPropertyDescriptions,
        private readonly array $propertyDiscriminators,
    ) {
        Assert::allIsInstanceOf($this->propertySchemas, Schema::class);
        Assert::allIsInstanceOf($this->propertyDiscriminators, Discriminator::class);
    }

    public function getType(): string
    {
        return 'object';
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

    /** @phpstan-assert-if-true object $value */
    public function isInstance(mixed $value): bool
    {
        return is_object($value) && $this->reflectionClass->isInstance($value);
    }

    public function instantiate(mixed $value): mixed
    {
        if ($this->isInstance($value)) {
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
                $propertySchema = $this->applyCustomDiscriminator($propertyName, $propertySchema);
                try {
                    $result[$propertyName] = $propertySchema->instantiate($array[$propertyName]);
                } catch (InvalidSchemaException $e) {
                    throw new InvalidSchemaException(sprintf('Invalid schema for property "%s" of type "%s": %s', $propertyName, $this->getName(), $e->getMessage()), 1734008510, $e);
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

    private function applyCustomDiscriminator(string $propertyName, Schema $propertySchema): Schema
    {
        if (!array_key_exists($propertyName, $this->propertyDiscriminators)) {
            return $propertySchema;
        }
        $supportedPropertySchemas = [OneOfSchema::class, InterfaceSchema::class, OptionalSchema::class];
        if (!in_array($propertySchema::class, $supportedPropertySchemas, true)) {
            throw new InvalidSchemaException(sprintf('Class "%s" has a %s attribute for property "%s" but the corresponding property schema is of type %s which is not one of the supported schema types %s', $this->getName(), Discriminator::class, $propertyName, get_debug_type($propertySchema), implode(', ', $supportedPropertySchemas)));
        }
        /** @var OneOfSchema|InterfaceSchema|OptionalSchema $propertySchema */
        try {
            return $propertySchema->withDiscriminator($this->propertyDiscriminators[$propertyName]);
        } catch (InvalidSchemaException $e) {
            throw new InvalidSchemaException(sprintf('Class "%s" incorrectly has a %s attribute for property "%s": %s', $this->getName(), Discriminator::class, $propertyName, $e->getMessage()), 1734543079, $e);
        }
    }
}
