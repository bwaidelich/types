<?php

declare(strict_types=1);

namespace Wwwision\Types\Attributes;

use Attribute;
use Wwwision\Types\Schema\StringTypeFormat;

#[Attribute(Attribute::TARGET_CLASS)]
final class StringBased implements TypeBased
{
    public function __construct(
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly ?string $pattern = null,
        public readonly ?StringTypeFormat $format = null,
    ) {}
}
