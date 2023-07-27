<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use InvalidArgumentException;
use Stringable;

use function get_debug_type;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * @implements Schema<int>
 */
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
            $value = (string)$value;
            $intValue = (int)$value;
            if ((string)$intValue !== $value) {
                throw new InvalidArgumentException(sprintf('Value "%s" cannot be casted to integer', $value));
            }
        } elseif (is_float($value)) {
            $intValue = (int)$value;
            if (((float)$intValue) !== $value) {
                throw new InvalidArgumentException(sprintf('Value %.3F cannot be casted to integer', $value));
            }
        } else {
            if (!is_int($value)) {
                throw new InvalidArgumentException(sprintf('Value of type %s cannot be casted to integer', get_debug_type($value)));
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
