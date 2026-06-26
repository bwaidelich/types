<?php

declare(strict_types=1);

namespace Wwwision\Types;

use Wwwision\Types\Schema\Dynamic\DynamicRecord;
use Wwwision\Types\Schema\Dynamic\DynamicValue;
use Wwwision\Types\Schema\Dynamic\ShapeExtender;
use Wwwision\Types\Schema\Schema;
use Wwwision\Types\Schema\ShapeSchema;
use Wwwision\Types\Schema\StringSchema;
use Wwwision\Types\Schema\StringTypeFormat;
use Wwwision\Types\Schema\Target\DynamicTarget;

use function reset;

/**
 * Builds schemas that are NOT backed by a PHP class. The result is an ordinary {@see StringSchema} /
 * {@see ShapeSchema} (no separate "dynamic" schema type) – only the injected
 * {@see DynamicTarget} differs, so 3rd-party consumers see no difference.
 */
final class DynamicSchema
{
    private function __construct() {}

    /**
     * @param array<string>|null $examples
     * @param array<string, mixed>|null $extensions
     */
    public static function string(
        string $name,
        string|null $description = null,
        int|null $minLength = null,
        int|null $maxLength = null,
        string|null $pattern = null,
        StringTypeFormat|null $format = null,
        array|null $examples = null,
        array|null $extensions = null,
    ): StringSchema {
        $target = new DynamicTarget($name, static fn(array $arguments) => new DynamicValue($name, reset($arguments)));
        return new StringSchema($target, $description, $minLength, $maxLength, $pattern, $format, $examples, $extensions);
    }

    /**
     * @param array<non-empty-string, Schema> $properties
     */
    public static function shape(string $name, array $properties, string|null $description = null): ShapeSchema
    {
        $target = new DynamicTarget($name, static fn(array $arguments) => new DynamicRecord($name, $arguments));
        return new ShapeSchema($target, $description, $properties, [], []);
    }

    /**
     * Starts extending an existing (class-based or dynamic) shape schema. Adding/removing properties
     * produces a binding-less {@see ShapeSchema} that instantiates to a {@see DynamicRecord}; inherited
     * properties keep their original (e.g. class-based) schemas, so their values stay real VOs.
     */
    public static function extend(ShapeSchema $base, string $name): ShapeExtender
    {
        return new ShapeExtender($name, $base);
    }
}
