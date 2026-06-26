<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Target;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Webmozart\Assert\Assert;
use Wwwision\Types\Schema\Schema;

use function sprintf;

/**
 * A {@see Target} backed by a real PHP class. Reproduces the original reflection-based behavior:
 * names itself from the class, checks `instanceof`, and constructs via the private constructor.
 */
final class ClassTarget implements Target
{
    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    public function __construct(
        public readonly ReflectionClass $reflectionClass,
    ) {}

    public function name(): string
    {
        return $this->reflectionClass->getShortName();
    }

    public function isInstance(mixed $value): bool
    {
        return is_object($value) && $this->reflectionClass->isInstance($value);
    }

    public function construct(Schema $schema, array $arguments): mixed
    {
        $constructor = $this->reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $this->reflectionClass->getName()));
        try {
            $instance = $this->reflectionClass->newInstanceWithoutConstructor();
            $constructor->invoke($instance, ...$arguments);
            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            throw new RuntimeException(sprintf('Failed to instantiate "%s": %s', $this->name(), $e->getMessage()), 1688570532, $e);
        }
        // @codeCoverageIgnoreEnd
        return $instance;
    }
}
