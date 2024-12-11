<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Webmozart\Assert\Assert;
use Wwwision\Types\Exception\CoerceException;
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
    ) {
        Assert::allIsInstanceOf($this->subSchemas, Schema::class);
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
        throw CoerceException::custom(sprintf('The given "__type" of "%s" is not an implementation of %s', $type, $this->getName()), $value, $this);
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
