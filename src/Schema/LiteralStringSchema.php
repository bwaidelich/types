<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use InvalidArgumentException;
use Stringable;

use function is_int;
use function is_string;
use function sprintf;

final class LiteralStringSchema implements Schema
{
    public function __construct(
        public readonly ?string $description,
    ) {
    }

    public function getType(): string
    {
        return 'string';
    }

    public function getName(): string
    {
        return 'string';
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function instantiate(mixed $value): string
    {
        return $this->coerce($value);
    }

    private function coerce(mixed $value): string
    {
        if (is_int($value) || $value instanceof Stringable) {
            $value = (string)$value;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Value of type %s cannot be casted to string', get_debug_type($value)));
        }
        return $value;
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
