<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Stringable;
use Wwwision\Types\Exception\CoerceException;

use function is_float;
use function is_int;
use function is_string;

final class LiteralIntegerSchema implements Schema
{
    public function __construct(
        public readonly ?string $description,
    ) {
    }

    public function getType(): string
    {
        return 'integer';
    }

    public function getName(): string
    {
        return 'int';
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function instantiate(mixed $value): int
    {
        return $this->coerce($value);
    }

    private function coerce(mixed $value): int
    {
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

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
        ];
    }
}
