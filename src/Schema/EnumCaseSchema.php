<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use UnitEnum;

final class EnumCaseSchema implements Schema
{
    public function __construct(
        private readonly ReflectionEnumUnitCase $reflectionClass,
        public readonly ?string $description,
    ) {
    }

    public function getType(): string
    {
        return $this->reflectionClass instanceof ReflectionEnumBackedCase ? gettype($this->reflectionClass->getBackingValue()) : 'string';
    }

    public function getName(): string
    {
        return $this->reflectionClass->getValue()->name;
    }

    public function getValue(): string|int
    {
        return $this->reflectionClass instanceof ReflectionEnumBackedCase ? $this->reflectionClass->getBackingValue() : $this->reflectionClass->getName();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function instantiate(mixed $value): UnitEnum
    {
        return $this->reflectionClass->getValue();
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'description' => $this->getDescription(),
            'name' => $this->getName(),
            'value' => $this->getValue(),
        ];
    }
}
