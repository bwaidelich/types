<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;
use Wwwision\Types\Schema\StringTypeFormat;

#[Attribute(Attribute::TARGET_CLASS)]
final class StringBased implements TypeBased
{
    public function __construct(
        public readonly null|int $minLength = null,
        public readonly null|int $maxLength = null,
        public readonly null|string $pattern = null,
        public readonly null|StringTypeFormat $format = null,
    ) {}
}
