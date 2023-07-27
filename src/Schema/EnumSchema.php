<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use BackedEnum;
use InvalidArgumentException;
use ReflectionEnum;
use ReflectionEnumUnitCase;
use ReflectionNamedType;
use Stringable;
use UnitEnum;
use ValueError;

use function get_debug_type;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * @template T of ReflectionEnumUnitCase
 * @implements Schema<T>
 */
final class EnumSchema implements Schema
{
    /**
     * @param array<string|int, EnumCaseSchema<T>> $caseSchemas
     */
    public function __construct(
        private readonly ReflectionEnum $reflectionClass,
        public readonly ?string $description,
        public readonly array $caseSchemas,
    ) {
    }

    public function getType(): string
    {
        return 'enum';
    }

    public function getName(): string
    {
        return $this->reflectionClass->getShortName();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getBackingType(): string
    {
        $backingType = $this->reflectionClass->getBackingType();
        if ($backingType instanceof ReflectionNamedType && $backingType->getName() === 'int') {
            return 'int';
        }
        return 'string';
    }

    public function instantiate(mixed $value): UnitEnum
    {
        $coercedValue = $this->coerce($value);
        if ($this->reflectionClass->isBacked()) {
            /** @var class-string<BackedEnum> $enumClass */
            $enumClass = $this->reflectionClass->getName();
            try {
                return $enumClass::from($coercedValue);
            } catch (ValueError $_) {
                if (is_int($value)) {
                    throw new InvalidArgumentException(sprintf('Value %s is not a valid enum case', $coercedValue));
                }
                throw new InvalidArgumentException(sprintf('Value "%s" is not a valid enum case', $coercedValue));
            }
        }
        foreach ($this->caseSchemas as $caseSchema) {
            if ($caseSchema->getName() === $coercedValue) {
                return $caseSchema->instantiate($coercedValue);
            }
        }
        if (is_int($value)) {
            throw new InvalidArgumentException(sprintf('Value %s is not a valid enum case', $coercedValue));
        }
        throw new InvalidArgumentException(sprintf('Value "%s" is not a valid enum case', $coercedValue));
    }

    private function coerce(mixed $value): string|int
    {
        if ($this->getBackingType() === 'int') {
            if (is_string($value) || $value instanceof Stringable) {
                $value = (string)$value;
                $intValue = (int)$value;
                if ((string)$intValue !== $value) {
                    throw new InvalidArgumentException(sprintf('Value "%s" cannot be casted to int backed enum', $value));
                }
            } elseif (is_float($value)) {
                $intValue = (int)$value;
                if (((float)$intValue) !== $value) {
                    throw new InvalidArgumentException(sprintf('Value of type %.3F cannot be casted to int backed enum', $value));
                }
            } else {
                if (!is_int($value)) {
                    throw new InvalidArgumentException(sprintf('Value of type %s cannot be casted to int backed enum', get_debug_type($value)));
                }
                $intValue = $value;
            }
            return $intValue;
        }
        if (is_int($value) || $value instanceof Stringable) {
            $value = (string)$value;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Value of type %s cannot be casted to string backed enum', get_debug_type($value)));
        }
        return $value;
    }

    public function jsonSerialize(): array
    {
        $cases = [];
        foreach ($this->caseSchemas as $caseSchema) {
            $cases[] = $caseSchema->jsonSerialize();
        }
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'cases' => $cases,
        ];
    }
}
