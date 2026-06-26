<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Dynamic;

use Wwwision\Types\Schema\Schema;

/**
 * Marker for values produced by a {@see \Wwwision\Types\Schema\Target\DynamicTarget}. Exposes the
 * {@see Schema} it was instantiated from, so consumers (e.g. integrations) can introspect the type –
 * its name, properties, item type, constraints, ... – even though no dedicated PHP class exists.
 *
 * Accessed via a method (rather than a property) so that {@see DynamicRecord}'s magic property space
 * stays free for actual record properties of any name.
 */
interface DynamicInstance
{
    public function getSchema(): Schema;
}
