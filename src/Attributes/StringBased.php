<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;
use Wwwision\Types\Schema\StringTypeFormat;

#[Attribute(Attribute::TARGET_CLASS)]
final class StringBased implements TypeBased
{
    /**
     * @param array<string>|null $examples
     */
    public function __construct(
        public readonly int|null $minLength = null,
        public readonly int|null $maxLength = null,
        public readonly string|null $pattern = null,
        public readonly StringTypeFormat|null $format = null,
        public readonly array|null $examples = null,
    ) {}
}
