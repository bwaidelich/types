<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Webmozart\Assert\Assert;

use function count;
use function is_iterable;
use function sprintf;

/**
 * @template T of object
 * @template TItem of object
 * @implements Schema<T>
 */
final class ListSchema implements Schema
{
    /**
     * @param ReflectionClass<T> $reflectionClass
     * @param Schema<TItem> $itemSchema
     */
    public function __construct(
        private readonly ReflectionClass $reflectionClass,
        public readonly ?string $description,
        public readonly Schema $itemSchema,
        public readonly ?int $minCount = null,
        public readonly ?int $maxCount = null,
    ) {
    }

    public function getType(): string
    {
        return 'array';
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
        $arrayValue = $this->coerce($value);
        $constructor = $this->reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $this->reflectionClass->getName()));
        try {
            /** @var T $instance */
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
            throw new InvalidArgumentException(sprintf('Non-iterable value of type %s cannot be casted to list of %s', get_debug_type($value), $this->itemSchema->getName()));
        }
        $converted = [];
        foreach ($value as $key => $itemValue) {
            try {
                $converted[] = $this->itemSchema->instantiate($itemValue);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException(sprintf('At key "%s": %s', $key, $e->getMessage()), 1688674403, $e);
            }
        }
        $count = count($converted);
        if ($this->minCount !== null && $count < $this->minCount) {
            throw new InvalidArgumentException(sprintf('Number of elements (%d) is less than allowed min count of %d', $count, $this->minCount), 1688674488);
        }
        if ($this->maxCount !== null && $count > $this->maxCount) {
            throw new InvalidArgumentException(sprintf('Number of elements (%d) is more than allowed max count of %d', $count, $this->maxCount), 1688674506);
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
