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

final class FloatSchema implements Schema
{
    /**
     * @param array<float|int>|null $examples
     * @param array<string, mixed>|null $extensions
     */
    public function __construct(
        private readonly Target $target,
        public readonly string|null $description,
        public readonly float|int|null $minimum = null,
        public readonly float|int|null $maximum = null,
        public readonly array|null $examples = null,
        public readonly array|null $extensions = null,
    ) {}

    public function getType(): string
    {
        return 'float';
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
        $floatValue = $this->coerce($value);
        return $this->target->construct([$floatValue]);
    }

    private function coerce(mixed $value): float
    {
        if (is_string($value) || $value instanceof Stringable) {
            $stringValue = (string) $value;
            if (ctype_digit($stringValue) || preg_match('/^[+-]?(\d+([.]\d*)?([eE][+-]?\d+)?|[.]\d+([eE][+-]?\d+)?)$/', $stringValue) === 1) {
                $floatValue = (float) $stringValue;
            } else {
                throw CoerceException::invalidType($value, $this);
            }
        } elseif (is_int($value) || is_float($value)) {
            $floatValue = (float) $value;
        } else {
            throw CoerceException::invalidType($value, $this);
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
        return $floatValue;
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
