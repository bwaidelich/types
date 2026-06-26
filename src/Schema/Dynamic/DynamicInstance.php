<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Dynamic;

/**
 * Marker for values produced by a {@see \Wwwision\Types\Schema\Target\DynamicTarget}. Carries the
 * schema's name so a dynamic value remains distinguishable at runtime even though `instanceof`
 * cannot tell two dynamic types apart.
 */
interface DynamicInstance
{
    public string $typeName { get; }
}
