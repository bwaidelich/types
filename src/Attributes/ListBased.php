<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ListBased implements TypeBased
{
    /**
     * @param class-string<object> $itemClassName
     * @param array<string, mixed>|null $extensions
     */
    public function __construct(
        public readonly string $itemClassName,
        public readonly int|null $minCount = null,
        public readonly int|null $maxCount = null,
        public readonly array|null $extensions = null,
    ) {}
}
