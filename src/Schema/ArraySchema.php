<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Stringable;
use Wwwision\Types\Exception\CoerceException;

use function is_float;
use function is_int;
use function is_string;

final class ArraySchema implements Schema
{
    public function __construct(
        public readonly ?string $description,
    ) {
    }

    public function getType(): string
    {
        return 'array';
    }

    public function getName(): string
    {
        return 'array';
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function instantiate(mixed $value): array
    {
        return $this->coerce($value);
    }

    private function coerce(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_iterable($value)) {
            return iterator_to_array($value);
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
