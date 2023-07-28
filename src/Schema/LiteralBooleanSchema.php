<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use InvalidArgumentException;

use function get_debug_type;
use function sprintf;

final class LiteralBooleanSchema implements Schema
{
    public function __construct(
        public readonly ?string $description,
    ) {
    }

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

    public function instantiate(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === '0') {
            return false;
        }
        if ($value === 1 || $value === '1') {
            return true;
        }
        if (is_string($value)) {
            throw new InvalidArgumentException(sprintf('Value "%s" cannot be casted to boolean', $value));
        }
        if (is_int($value)) {
            throw new InvalidArgumentException(sprintf('Value %d cannot be casted to boolean', $value));
        }
        throw new InvalidArgumentException(sprintf('Value of type %s cannot be casted to boolean', get_debug_type($value)));
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
