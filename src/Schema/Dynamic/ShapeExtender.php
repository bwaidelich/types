<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Dynamic;

use Wwwision\Types\Schema\Schema;
use Wwwision\Types\Schema\ShapeSchema;
use Wwwision\Types\Schema\Target\DynamicTarget;

/**
 * Immutable builder for extending a {@see ShapeSchema}. Reads the base schema's public
 * `propertySchemas`, lets you add/remove/override properties, and builds a binding-less
 * {@see ShapeSchema} (instantiating to a {@see DynamicRecord}).
 */
final class ShapeExtender
{
    /**
     * @var array<non-empty-string, Schema>
     */
    private array $propertySchemas;

    private string|null $description;

    public function __construct(
        private readonly string $name,
        ShapeSchema $base,
    ) {
        $this->propertySchemas = $base->propertySchemas;
        $this->description = $base->getDescription();
    }

    /**
     * @param non-empty-string $name
     */
    public function withProperty(string $name, Schema $schema): self
    {
        $clone = clone $this;
        $clone->propertySchemas[$name] = $schema;
        return $clone;
    }

    /**
     * @param non-empty-string $name
     */
    public function withoutProperty(string $name): self
    {
        $clone = clone $this;
        unset($clone->propertySchemas[$name]);
        return $clone;
    }

    public function withDescription(string|null $description): self
    {
        $clone = clone $this;
        $clone->description = $description;
        return $clone;
    }

    public function build(): ShapeSchema
    {
        return new ShapeSchema(DynamicTarget::record($this->name), $this->description, $this->propertySchemas, [], []);
    }
}
