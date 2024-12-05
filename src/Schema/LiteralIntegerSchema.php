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

    /** @phpstan-assert-if-true int $value */
    public function isInstance(mixed $value): bool
    {
        return is_int($value);
    }

    public function instantiate(mixed $value): int
    {
        if ($this->isInstance($value)) {
            return $value;
        }
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
            throw CoerceException::invalidType($value, $this);
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
