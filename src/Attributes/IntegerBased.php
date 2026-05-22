<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class IntegerBased implements TypeBased
{
    /**
     * @param array<int>|null $examples
     * @param array<string, mixed>|null $extensions
     */
    public function __construct(
        public readonly int|null $minimum = null,
        public readonly int|null $maximum = null,
        public readonly array|null $examples = null,
        public readonly array|null $extensions = null,
    ) {}
}
