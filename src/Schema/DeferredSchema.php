<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Closure;
use Wwwision\Types\Options;

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
        return $this->resolve()->getType();
    }

    public function getName(): string
    {
        return $this->resolve()->getName();
    }

    public function getDescription(): string|null
    {
        return $this->resolve()->getDescription();
    }

    public function isInstance(mixed $value): bool
    {
        return $this->resolve()->isInstance($value);
    }

    public function instantiate(mixed $value, Options $options): mixed
    {
        return $this->resolve()->instantiate($value, $options);
    }

    public function jsonSerialize(): array
    {
        return $this->resolve()->jsonSerialize();
    }

    public function resolve(): Schema
    {
        if ($this->resolvedSchema === null) {
            $this->resolvedSchema = ($this->schemaResolver)();
        }
        return $this->resolvedSchema;
    }
}
