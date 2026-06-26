<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\Issues\Issues;
use Wwwision\Types\Options;
use Wwwision\Types\Schema\Target\Target;

use function is_iterable;

final class ListSchema implements Schema
{
    /**
     * @param array<string, mixed>|null $extensions
     */
    public function __construct(
        private readonly Target $target,
        public readonly string|null $description,
        public readonly Schema $itemSchema,
        public readonly int|null $minCount = null,
        public readonly int|null $maxCount = null,
        public readonly array|null $extensions = null,
    ) {}

    public function getType(): string
    {
        return 'array';
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
        $arrayValue = $this->coerce($value, $options);
        return $this->target->construct([$arrayValue]);
    }

    /**
     * @return array<mixed>
     */
    private function coerce(mixed $value, Options $options): array
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
                $converted[$key] = $this->itemSchema->instantiate($itemValue, $options);
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
        if ($this->extensions !== null) {
            $result += $this->extensions;
        }
        return $result;
    }
}
