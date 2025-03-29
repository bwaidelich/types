<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use RuntimeException;
use Webmozart\Assert\Assert;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\InvalidSchemaException;
use Wwwision\Types\Parser;

use function array_key_exists;
use function get_object_vars;
use function is_array;
use function is_iterable;
use function is_object;
use function iterator_to_array;

final class OneOfSchema implements Schema
{
    /**
     * @param array<Schema> $subSchemas
     */
    public function __construct(
        public readonly array $subSchemas,
        private readonly ?string $description,
        public readonly ?Discriminator $discriminator,
    ) {
        Assert::allIsInstanceOf($this->subSchemas, Schema::class);
    }

    public function withDiscriminator(Discriminator $discriminator): self
    {
        return new self($this->subSchemas, $this->description, $discriminator);
    }

    public function getType(): string
    {
        return implode('|', array_map(static fn(Schema $subSchema) => $subSchema->getName(), $this->subSchemas));
    }

    public function getName(): string
    {
        return implode('|', array_map(static fn(Schema $subSchema) => $subSchema->getName(), $this->subSchemas));
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isInstance(mixed $value): bool
    {
        foreach ($this->subSchemas as $subSchema) {
            if ($subSchema->isInstance($value)) {
                return true;
            }
        }
        return false;
    }

    public function instantiate(mixed $value): mixed
    {
        if ($this->isInstance($value)) {
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
            $nonLiteralSubSchemas = array_filter($this->subSchemas, static fn(Schema $subSchema) => !($subSchema instanceof LiteralStringSchema || $subSchema instanceof LiteralIntegerSchema || $subSchema instanceof LiteralFloatSchema || $subSchema instanceof LiteralBooleanSchema || $subSchema instanceof LiteralNullSchema));
            if (count($nonLiteralSubSchemas) === 1) {
                return $nonLiteralSubSchemas[0]->instantiate($value);
            }
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
                throw new InvalidSchemaException(sprintf('Discriminator mapping refers to non-existing class "%s"', $type), 1734005363);
            }
        } elseif (!class_exists($type)) {
            throw CoerceException::custom(sprintf('Discriminator key "%s" has to be a valid class name, got: "%s"', $discriminatorPropertyName, $type), $value, $this);
        }
        if (property_exists($type, $discriminatorPropertyName)) {
            throw new InvalidSchemaException(sprintf('Discriminator key "%s" of type "%s" is ambiguous with the property "%s" of implementation "%s"', $discriminatorPropertyName, $this->getName(), $discriminatorPropertyName, $type), 1734624810);
        }
        if (isset($array['__value'])) {
            $array = $array['__value'];
        } else {
            unset($array[$discriminatorPropertyName]);
        }
        if ($array === []) {
            throw CoerceException::custom(sprintf('Missing keys for union of type %s', $this->getName()), $value, $this);
        }
        try {
            $result = Parser::instantiate($type, $array);
        } catch (CoerceException $e) {
            throw CoerceException::fromIssues($e->issues, $value, $this);
        }
        foreach ($this->subSchemas as $subSchema) {
            if ($subSchema->isInstance($result)) {
                return $result;
            }
        }
        throw CoerceException::custom(sprintf('The given "%s" of "%s" is not an implementation of %s', $discriminatorPropertyName, $type, $this->getName()), $value, $this);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'subSchemas' => $this->subSchemas,
        ];
    }
}
