<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class FloatBased implements TypeBased
{
    public function __construct(
        public readonly float|int|null $minimum = null,
        public readonly float|int|null $maximum = null,
    ) {}
}
