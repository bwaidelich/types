<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception\Issues;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<Issue>
 */
final class Issues implements IteratorAggregate, JsonSerializable
{
    /**
     * @var array<Issue>
     */
    private readonly array $items;

    private function __construct(Issue ...$items)
    {
        $this->items = $items;
    }

    public static function create(Issue $issue): self
    {
        return new self($issue);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function add(Issue|self $issuesToAdd, string|int|null $pathSegment = null): self
    {
        if ($issuesToAdd instanceof Issue) {
            $issuesToAdd = self::create($issuesToAdd);
        }
        if ($pathSegment === null) {
            return new self(...[...$this->items, ...$issuesToAdd->items]);
        }
        $newItems = $this->items;
        foreach ($issuesToAdd as $issue) {
            $newItems[] = $issue->withPrependedPathSegment($pathSegment);
        }
        return new self(...$newItems);
    }

    public function prepend(Issue|self $issuesToPrepend): self
    {
        if ($issuesToPrepend instanceof Issue) {
            $issuesToPrepend = self::create($issuesToPrepend);
        }
        return new self(...[...$issuesToPrepend->items, ...$this->items]);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array<Issue>
     */
    public function jsonSerialize(): array
    {
        return array_values($this->items);
    }

    public function withPrependedPathSegment(string|int $pathSegment): self
    {
        return new self(...array_map(static fn (Issue $issue) => $issue->withPrependedPathSegment($pathSegment), $this->items));
    }

    /**
     * @param Closure(Issue): mixed $callback
     * @return array<mixed>
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->items);
    }
}
