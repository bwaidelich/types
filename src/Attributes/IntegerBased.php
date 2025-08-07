<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class IntegerBased implements TypeBased
{
    /**
     * @param array<int>|null $examples
     */
    public function __construct(
        public readonly null|int $minimum = null,
        public readonly null|int $maximum = null,
        public readonly null|array $examples = null,
    ) {}
}
