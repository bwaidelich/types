<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Stringable;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\Issues\Issues;
use Wwwision\Types\Options;
use Wwwision\Types\Schema\Target\Target;

use function is_float;
use function is_int;
use function is_string;

final class IntegerSchema implements Schema
{
    /**
     * @param array<int>|null $examples
     * @param array<string, mixed>|null $extensions
     */
    public function __construct(
        private readonly Target $target,
        public readonly string|null $description,
        public readonly int|null $minimum = null,
        public readonly int|null $maximum = null,
        public readonly array|null $examples = null,
        public readonly array|null $extensions = null,
    ) {}

    public function getType(): string
    {
        return 'integer';
    }

    public function getName(): string
    {
        return $this->target->name();
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function isInstance(mixed $value): bool
    {
        return $this->target->isInstance($value);
    }

    public function instantiate(mixed $value, Options $options): mixed
    {
        if ($this->target->isInstance($value)) {
            return $value;
        }
        $intValue = $this->coerce($value);
        return $this->target->construct($this, [$intValue]);
    }

    private function coerce(mixed $value): int
    {
        if (is_string($value) || $value instanceof Stringable) {
            $intValue = (int) ((string) $value);
            if ((string) $intValue !== (string) $value) {
                throw CoerceException::invalidType($value, $this);
            }
        } elseif (is_float($value)) {
            $intValue = (int) $value;
            if (((float) $intValue) !== $value) {
                throw CoerceException::invalidType($value, $this);
            }
        } else {
            if (!is_int($value)) {
                throw CoerceException::invalidType($value, $this);
            }
            $intValue = $value;
        }
        $issues = Issues::empty();
        if ($this->maximum !== null && $value > $this->maximum) {
            $issues = $issues->add(CoerceException::tooBig($value, $this, $this->maximum, true, $this->minimum === $this->maximum)->issues);
        }
        if ($this->minimum !== null && $value < $this->minimum) {
            $issues = $issues->add(CoerceException::tooSmall($value, $this, $this->minimum, true, $this->minimum === $this->maximum)->issues);
        }
        if (!$issues->isEmpty()) {
            throw CoerceException::fromIssues($issues, $value, $this);
        }
        return $intValue;
    }

    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
        ];
        if ($this->minimum !== null) {
            $result['minimum'] = $this->minimum;
        }
        if ($this->maximum !== null) {
            $result['maximum'] = $this->maximum;
        }
        if ($this->examples !== null) {
            $result['examples'] = $this->examples;
        }
        if ($this->extensions !== null) {
            $result += $this->extensions;
        }
        return $result;
    }
}
