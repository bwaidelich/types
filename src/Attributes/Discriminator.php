<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class Discriminator
{
    /**
     * @param string $propertyName
     * @param array<non-empty-string, class-string>|null $mapping
     */
    public function __construct(
        public readonly string $propertyName,
        public readonly array|null $mapping = null,
    ) {}
}
