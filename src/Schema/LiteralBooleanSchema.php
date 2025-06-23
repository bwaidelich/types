<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Wwwision\Types\Exception\CoerceException;

final class LiteralBooleanSchema implements Schema
{
    public function __construct(
        public readonly ?string $description,
    ) {}

    public function getType(): string
    {
        return 'boolean';
    }

    public function getName(): string
    {
        return 'boolean';
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @phpstan-assert-if-true bool $value */
    public function isInstance(mixed $value): bool
    {
        return is_bool($value);
    }

    public function instantiate(mixed $value): bool
    {
        if ($this->isInstance($value)) {
            return $value;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        throw CoerceException::invalidType($value, $this);
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
