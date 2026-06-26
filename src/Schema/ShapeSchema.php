<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use InvalidArgumentException;
use Webmozart\Assert\Assert;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\InvalidSchemaException;
use Wwwision\Types\Exception\Issues\Issues;
use Wwwision\Types\Options;
use Wwwision\Types\Schema\Target\Target;

use function array_diff_key;
use function array_key_exists;
use function get_debug_type;
use function get_object_vars;
use function is_array;
use function is_iterable;
use function is_object;
use function iterator_to_array;
use function sprintf;

final class ShapeSchema implements Schema
{
    /**
     * @param array<non-empty-string, Schema> $propertySchemas
     * @param array<non-empty-string, string> $overriddenPropertyDescriptions
     * @param array<non-empty-string, Discriminator> $propertyDiscriminators
     * @param array<non-empty-string, mixed> $propertyDefaults the raw constructor default values, keyed by property name (presence means "has a default", so a default of null is distinguishable from "no default")
     */
    public function __construct(
        private readonly Target $target,
        public readonly string|null $description,
        public readonly array $propertySchemas,
        private readonly array $overriddenPropertyDescriptions,
        private readonly array $propertyDiscriminators,
        private readonly array $propertyDefaults = [],
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
        return $this->target->name();
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function overriddenPropertyDescription(string $propertyName): string|null
    {
        return $this->overriddenPropertyDescriptions[$propertyName] ?? null;
    }

    /**
     * Whether the property with the given name has a constructor default value.
     *
     * Note: This is distinct from the property being optional – a property of type `?string $foo` (without
     * default) is optional but has no default, whereas `string $foo = 'bar'` has the default value "bar".
     */
    public function hasDefaultValue(string $propertyName): bool
    {
        return array_key_exists($propertyName, $this->propertyDefaults);
    }

    /**
     * The raw constructor default value of the property with the given name (e.g. the string "bar" for
     * `string $foo = 'bar'`, or an enum case for `Suit $suit = Suit::Hearts`).
     *
     * @throws InvalidArgumentException if the property has no default value – use {@see self::hasDefaultValue()} to check
     */
    public function defaultValue(string $propertyName): mixed
    {
        if (!array_key_exists($propertyName, $this->propertyDefaults)) {
            throw new InvalidArgumentException(sprintf('Property "%s" of type "%s" has no default value', $propertyName, $this->getName()), 1782518400);
        }
        return $this->propertyDefaults[$propertyName];
    }

    public function isInstance(mixed $value): bool
    {
        return $this->target->isInstance($value);
    }

    public function instantiate(mixed $value, Options $options): mixed
    {
        if ($this->target->isInstance($value)) {
            return $value;
        }
        $arrayValue = $this->coerce($value, $options);
        return $this->target->construct($this, $arrayValue);
    }

    /**
     * @return array<mixed>
     */
    private function coerce(mixed $value, Options $options): array
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
                    $result[$propertyName] = $propertySchema->instantiate($array[$propertyName], $options);
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
        if (!$options->ignoreUnrecognizedKeys) {
            $unrecognizedKeys = array_diff_key($array, $this->propertySchemas);
            if ($unrecognizedKeys !== []) {
                $issues = $issues->add(CoerceException::unrecognizedKeys($value, $this, array_keys($unrecognizedKeys))->issues);
            }
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
