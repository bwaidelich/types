<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Webmozart\Assert\Assert;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\Issues\Issues;

use function is_iterable;
use function sprintf;

final class ListSchema implements Schema
{
    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    public function __construct(
        private readonly ReflectionClass $reflectionClass,
        public readonly null|string $description,
        public readonly Schema $itemSchema,
        public readonly null|int $minCount = null,
        public readonly null|int $maxCount = null,
    ) {}

    public function getType(): string
    {
        return 'array';
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
        $arrayValue = $this->coerce($value);
        $constructor = $this->reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $this->reflectionClass->getName()));
        try {
            $instance = $this->reflectionClass->newInstanceWithoutConstructor();
            $constructor->invoke($instance, $arrayValue);
            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            throw new RuntimeException(sprintf('Failed to instantiate "%s": %s', $this->getName(), $e->getMessage()), 1688570532, $e);
        }
        // @codeCoverageIgnoreEnd
        return $instance;
    }

    /**
     * @return array<mixed>
     */
    private function coerce(mixed $value): array
    {
        if (!is_iterable($value)) {
            throw CoerceException::invalidType($value, $this);
        }
        $converted = [];
        $count = 0;
        $issues = Issues::empty();
        foreach ($value as $key => $itemValue) {
            $count++;
            try {
                $converted[$key] = $this->itemSchema->instantiate($itemValue);
            } catch (CoerceException $e) {
                $issues = $issues->add($e->issues, $key);
            }
        }
        if ($this->maxCount !== null && $count > $this->maxCount) {
            $issues = $issues->prepend(CoerceException::tooBig($value, $this, $this->maxCount, true, $this->minCount === $this->maxCount)->issues);
        }
        if ($this->minCount !== null && $count < $this->minCount) {
            $issues = $issues->prepend(CoerceException::tooSmall($value, $this, $this->minCount, true, $this->minCount === $this->maxCount)->issues);
        }
        if (!$issues->isEmpty()) {
            throw CoerceException::fromIssues($issues, $value, $this);
        }
        return $converted;
    }

    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'itemType' => $this->itemSchema->getName(),
        ];
        if ($this->minCount !== null) {
            $result['minCount'] = $this->minCount;
        }
        if ($this->maxCount !== null) {
            $result['maxCount'] = $this->maxCount;
        }
        return $result;
    }
}
