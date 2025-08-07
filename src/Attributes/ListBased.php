<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ListBased implements TypeBased
{
    /**
     * @param class-string<object> $itemClassName
     */
    public function __construct(
        public readonly string $itemClassName,
        public readonly null|int $minCount = null,
        public readonly null|int $maxCount = null,
    ) {}
}
