<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use BackedEnum;
use ReflectionEnum;
use ReflectionNamedType;
use Stringable;
use UnitEnum;
use ValueError;
use Wwwision\Types\Exception\CoerceException;

use function is_float;
use function is_int;
use function is_string;

final class EnumSchema implements Schema
{
    /**
     * @param array<string|int, EnumCaseSchema> $caseSchemas
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
        if (is_object($value) && $this->reflectionClass->isInstance($value)) {
            /** @var UnitEnum $value */
            return $value;
        }
        $coercedValue = $this->coerce($value);
        if ($this->reflectionClass->isBacked()) {
            /** @var class-string<BackedEnum> $enumClass */
            $enumClass = $this->reflectionClass->getName();
            try {
                return $enumClass::from($coercedValue);
            } catch (ValueError $_) {
                throw CoerceException::invalidEnumValue($value, $this);
            }
        }
        foreach ($this->caseSchemas as $caseSchema) {
            if ($caseSchema->getName() === $coercedValue) {
                return $caseSchema->instantiate($coercedValue);
            }
        }
        throw CoerceException::invalidEnumValue($value, $this);
    }

    private function coerce(mixed $value): string|int
    {
        if ($this->getBackingType() === 'int') {
            if (is_string($value) || $value instanceof Stringable) {
                $intValue = (int)((string)$value);
                if ((string)$intValue !== (string)$value) {
                    throw CoerceException::invalidType($value, $this);
                }
            } elseif (is_float($value)) {
                $intValue = (int)$value;
                if (((float)$intValue) !== $value) {
                    throw CoerceException::invalidType($value, $this);
                }
            } else {
                if (!is_int($value)) {
                    throw CoerceException::invalidType($value, $this);
                }
                $intValue = $value;
            }
            return $intValue;
        }
        if (is_int($value) || $value instanceof Stringable) {
            $value = (string)$value;
        }
        if (!is_string($value)) {
            throw CoerceException::invalidType($value, $this);
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
