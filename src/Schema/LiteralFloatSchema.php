<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Stringable;
use Wwwision\Types\Exception\CoerceException;

use function is_float;
use function is_int;
use function is_string;

final class LiteralFloatSchema implements Schema
{
    public function __construct(
        public readonly ?string $description,
    ) {
    }

    public function getType(): string
    {
        return 'float';
    }

    public function getName(): string
    {
        return 'float';
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function instantiate(mixed $value): float
    {
        return $this->coerce($value);
    }

    private function coerce(mixed $value): float
    {
        if (is_string($value) || $value instanceof Stringable) {
            $floatValue = (float)((string)$value);
            if ((string)$floatValue !== (string)$value) {
                throw CoerceException::invalidType($value, $this);
            }
        } elseif (is_float($value) || is_int($value)) {
            $floatValue = (float)$value;
        } else {
            throw CoerceException::invalidType($value, $this);
        }
        return $floatValue;
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
