<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

final class OptionalSchema implements Schema
{
    public function __construct(
        public readonly Schema $wrapped,
    ) {
    }

    public function getType(): string
    {
        return $this->wrapped->getType();
    }

    public function getName(): string
    {
        return $this->wrapped->getName();
    }

    public function getDescription(): ?string
    {
        return $this->wrapped->getDescription();
    }

    public function isInstance(mixed $value): bool
    {
        return $value === null || $this->wrapped->isInstance($value);
    }

    public function instantiate(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return $this->wrapped->instantiate($value);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'optional' => true,
        ];
    }
}
