<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Exception\InvalidSchemaException;

final class OptionalSchema implements Schema
{
    public function __construct(
        public readonly Schema $wrapped,
    ) {}

    public function withDiscriminator(Discriminator $discriminator): self
    {
        if (!$this->wrapped instanceof OneOfSchema && !$this->wrapped instanceof InterfaceSchema) {
            throw new InvalidSchemaException(sprintf('The schema for type "%s" is of type %s which is not one of the supported schema types %s', $this->getName(), $this->wrapped::class, implode(', ', [OneOfSchema::class, InterfaceSchema::class])));
        }
        return new self($this->wrapped->withDiscriminator($discriminator));
    }

    public function getType(): string
    {
        return $this->wrapped->getType();
    }

    public function getName(): string
    {
        return $this->wrapped->getName();
    }

    public function getDescription(): ?string
    {
        return $this->wrapped->getDescription();
    }

    public function isInstance(mixed $value): bool
    {
        return $value === null || $this->wrapped->isInstance($value);
    }

    public function instantiate(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return $this->wrapped->instantiate($value);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'optional' => true,
        ];
    }
}
