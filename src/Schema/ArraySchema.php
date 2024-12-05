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

    /** @phpstan-assert-if-true array $value */
    public function isInstance(mixed $value): bool
    {
        return is_array($value);
    }

    /**
     * @return array<mixed>
     */
    public function instantiate(mixed $value): array
    {
        if ($this->isInstance($value)) {
            return $value;
        }
        return $this->coerce($value);
    }

    /**
     * @return array<mixed>
     */
    private function coerce(mixed $value): array
    {
        assert(!is_array($value));
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
