<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Closure;

final class DeferredSchema implements Schema
{
    private Schema|null $resolvedSchema = null;

    /**
     * @param Closure(): Schema $schemaResolver
     */
    public function __construct(
        private readonly Closure $schemaResolver,
    ) {}

    public function getType(): string
    {
        return $this->resolveSchema()->getType();
    }

    public function getName(): string
    {
        return $this->resolveSchema()->getName();
    }

    public function getDescription(): ?string
    {
        return $this->resolveSchema()->getDescription();
    }

    public function isInstance(mixed $value): bool
    {
        return $this->resolveSchema()->isInstance($value);
    }

    public function instantiate(mixed $value): mixed
    {
        return $this->resolveSchema()->instantiate($value);
    }

    public function jsonSerialize(): array
    {
        return $this->resolveSchema()->jsonSerialize();
    }

    public function resolveSchema(): Schema
    {
        if ($this->resolvedSchema === null) {
            $this->resolvedSchema = ($this->schemaResolver)();
        }
        return $this->resolvedSchema;
    }
}
