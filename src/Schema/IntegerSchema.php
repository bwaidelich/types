<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Stringable;
use Webmozart\Assert\Assert;

use function get_debug_type;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * @template T of object
 * @implements Schema<T>
 */
final class IntegerSchema implements Schema
{
    /**
     * @param ReflectionClass<T> $reflectionClass
     */
    public function __construct(
        private readonly ReflectionClass $reflectionClass,
        public readonly ?string $description,
        public readonly ?int $minimum = null,
        public readonly ?int $maximum = null,
    ) {
    }

    public function getType(): string
    {
        return 'integer';
    }

    public function getName(): string
    {
        return $this->reflectionClass->getShortName();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function instantiate(mixed $value): object
    {
        $intValue = $this->coerce($value);
        $constructor = $this->reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $this->reflectionClass->getName()));
        try {
            /** @var T $instance */
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
            $value = (string)$value;
            $intValue = (int)$value;
            if ((string)$intValue !== $value) {
                throw new InvalidArgumentException(sprintf('Value "%s" cannot be casted to int', $value));
            }
        } elseif (is_float($value)) {
            $intValue = (int)$value;
            if (((float)$intValue) !== $value) {
                throw new InvalidArgumentException(sprintf('Value %.3F cannot be casted to int', $value));
            }
        } else {
            if (!is_int($value)) {
                throw new InvalidArgumentException(sprintf('Value of type %s cannot be casted to int', get_debug_type($value)));
            }
            $intValue = $value;
        }
        if ($this->minimum !== null && $value < $this->minimum) {
            throw new InvalidArgumentException(sprintf('Value %d falls below the allowed minimum value of %d', $value, $this->minimum));
        }
        if ($this->maximum !== null && $value > $this->maximum) {
            throw new InvalidArgumentException(sprintf('Value %d exceeds the allowed maximum value of %d', $value, $this->maximum));
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
        return $result;
    }
}
