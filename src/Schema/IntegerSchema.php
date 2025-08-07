<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Stringable;
use Webmozart\Assert\Assert;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\Issues\Issues;

use function is_float;
use function is_int;
use function is_string;
use function sprintf;

final class IntegerSchema implements Schema
{
    /**
     * @param ReflectionClass<object> $reflectionClass
     * @param array<int>|null $examples
     */
    public function __construct(
        private readonly ReflectionClass $reflectionClass,
        public readonly null|string $description,
        public readonly null|int $minimum = null,
        public readonly null|int $maximum = null,
        public readonly null|array $examples = null,
    ) {}

    public function getType(): string
    {
        return 'integer';
    }

    public function getName(): string
    {
        return $this->reflectionClass->getShortName();
    }

    public function getDescription(): null|string
    {
        return $this->description;
    }

    /** @phpstan-assert-if-true object $value */
    public function isInstance(mixed $value): bool
    {
        return is_object($value) && $this->reflectionClass->isInstance($value);
    }

    public function instantiate(mixed $value): object
    {
        if ($this->isInstance($value)) {
            return $value;
        }
        $intValue = $this->coerce($value);
        $constructor = $this->reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $this->reflectionClass->getName()));
        try {
            $instance = $this->reflectionClass->newInstanceWithoutConstructor();
            $constructor->invoke($instance, $intValue);
            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            throw new RuntimeException(sprintf('Failed to instantiate "%s": %s', $this->getName(), $e->getMessage()), 1688570532, $e);
        }
        // @codeCoverageIgnoreEnd
        return $instance;
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
        return $result;
    }
}
