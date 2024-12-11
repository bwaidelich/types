<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class IntegerBased implements TypeBased
{
    public function __construct(
        public readonly ?int $minimum = null,
        public readonly ?int $maximum = null,
    ) {}
}
