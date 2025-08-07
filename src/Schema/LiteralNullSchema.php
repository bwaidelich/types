<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

final class LiteralNullSchema implements Schema
{
    public function __construct(
        public readonly null|string $description,
    ) {}

    public function getType(): string
    {
        return 'null';
    }

    public function getName(): string
    {
        return 'null';
    }

    public function getDescription(): null|string
    {
        return $this->description;
    }

    /** @phpstan-assert-if-true null $value */
    public function isInstance(mixed $value): bool
    {
        return is_null($value);
    }

    public function instantiate(mixed $value): mixed
    {
        return null;
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
        ];
    }
}
