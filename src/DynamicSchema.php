<?php

declare(strict_types=1);

namespace Wwwision\Types;

use Wwwision\Types\Schema\Dynamic\ShapeExtender;
use Wwwision\Types\Schema\FloatSchema;
use Wwwision\Types\Schema\IntegerSchema;
use Wwwision\Types\Schema\ListSchema;
use Wwwision\Types\Schema\Schema;
use Wwwision\Types\Schema\ShapeSchema;
use Wwwision\Types\Schema\StringSchema;
use Wwwision\Types\Schema\StringTypeFormat;
use Wwwision\Types\Schema\Target\DynamicTarget;

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
        return new StringSchema(DynamicTarget::scalar($name), $description, $minLength, $maxLength, $pattern, $format, $examples, $extensions);
    }

    /**
     * @param array<int>|null $examples
     * @param array<string, mixed>|null $extensions
     */
    public static function integer(
        string $name,
        string|null $description = null,
        int|null $minimum = null,
        int|null $maximum = null,
        array|null $examples = null,
        array|null $extensions = null,
    ): IntegerSchema {
        return new IntegerSchema(DynamicTarget::scalar($name), $description, $minimum, $maximum, $examples, $extensions);
    }

    /**
     * @param array<float|int>|null $examples
     * @param array<string, mixed>|null $extensions
     */
    public static function float(
        string $name,
        string|null $description = null,
        float|int|null $minimum = null,
        float|int|null $maximum = null,
        array|null $examples = null,
        array|null $extensions = null,
    ): FloatSchema {
        return new FloatSchema(DynamicTarget::scalar($name), $description, $minimum, $maximum, $examples, $extensions);
    }

    /**
     * @param array<string, mixed>|null $extensions
     */
    public static function list(
        string $name,
        Schema $itemSchema,
        int|null $minCount = null,
        int|null $maxCount = null,
        string|null $description = null,
        array|null $extensions = null,
    ): ListSchema {
        return new ListSchema(DynamicTarget::list($name), $description, $itemSchema, $minCount, $maxCount, $extensions);
    }

    /**
     * @param array<non-empty-string, Schema> $properties
     */
    public static function shape(string $name, array $properties, string|null $description = null): ShapeSchema
    {
        return new ShapeSchema(DynamicTarget::record($name), $description, $properties, [], []);
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
