<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception;

use InvalidArgumentException;
use JsonSerializable;
use Webmozart\Assert\Assert;
use Wwwision\Types\Exception\Issues\Custom;
use Wwwision\Types\Exception\Issues\InvalidEnumValue;
use Wwwision\Types\Exception\Issues\InvalidString;
use Wwwision\Types\Exception\Issues\InvalidType;
use Wwwision\Types\Exception\Issues\Issue;
use Wwwision\Types\Exception\Issues\Issues;
use Wwwision\Types\Exception\Issues\TooBig;
use Wwwision\Types\Exception\Issues\TooSmall;
use Wwwision\Types\Exception\Issues\UnrecognizedKeys;
use Wwwision\Types\Schema\EnumCaseSchema;
use Wwwision\Types\Schema\EnumSchema;
use Wwwision\Types\Schema\IntegerSchema;
use Wwwision\Types\Schema\InterfaceSchema;
use Wwwision\Types\Schema\ListSchema;
use Wwwision\Types\Schema\Schema;
use Wwwision\Types\Schema\ShapeSchema;
use Wwwision\Types\Schema\StringSchema;

final class CoerceException extends InvalidArgumentException implements JsonSerializable
{
    /**
     * @const int maximum number of characters for normalized values in the exception message. Strings longer than this value will be truncated
     */
    private const MAXIMUM_STRING_LENGTH = 100;

    private function __construct(
        public readonly mixed $value,
        public readonly Schema $schema,
        public readonly Issues $issues,
    ) {
        parent::__construct(self::createMessage($value, $schema, $issues));
    }

    public static function fromIssues(Issues $issues, mixed $value, Schema $schema): self
    {
        return new self($value, $schema, $issues);
    }


    private static function createMessage(mixed $value, Schema $schema, Issues $issues): string
    {
        if (is_string($value)) {
            $normalizedValue = 'string of "' . (strlen($value) > self::MAXIMUM_STRING_LENGTH ? substr($value, 0, self::MAXIMUM_STRING_LENGTH - 5) . '[...]' : $value) . '"';
        } elseif (is_bool($value)) {
            $normalizedValue = 'boolean value ' . ($value ? 'true' : 'false');
        } elseif (is_float($value)) {
            $normalizedValue = 'float value of ' . number_format($value, 2);
        } elseif (is_int($value)) {
            $normalizedValue = 'integer value of ' . $value;
        } elseif ($value === null) {
            $normalizedValue = 'value of null';
        } else {
            $normalizedValue = 'value of type ' . get_debug_type($value);
        }
        if ($schema instanceof InterfaceSchema) {
            $normalizedSchemaName = 'instance of ' . $schema->getName();
        } else {
            $normalizedSchemaName = $schema->getName();
        }
        $issueMessages = $issues->map(function (Issue $issue) {
            $path = $issue->path() === [] ? '' : 'At "' . implode('.', array_map(static fn (string|int $segment) => trim((string)$segment, '\''), $issue->path())) . '": ';
            return $path . $issue->code()->name . ' (' . $issue->message() . ')';
        });
        return sprintf('Failed to cast %s to %s: %s', $normalizedValue, $normalizedSchemaName, implode('. ', $issueMessages));
    }

    public static function invalidType(mixed $value, Schema $schema): self
    {
        if ($schema instanceof InterfaceSchema) {
            $typeExpected = 'object';
        } elseif ($schema instanceof EnumSchema) {
            $typeExpected = implode(' | ', self::normalizeArrayValues(self::enumOptions($schema)));
        } else {
            $typeExpected = $schema->getType();
        }
        $typeReceived = is_null($value) ? 'null' : gettype($value);
        $issue = new InvalidType(sprintf('Expected %s, received %s', $typeExpected, $typeReceived), [], $schema, $typeReceived);
        return new self($value, $schema, Issues::create($issue));
    }

    public static function invalidEnumValue(mixed $value, EnumSchema $schema): self
    {
        if (is_string($value)) {
            $normalizedValue = '\'' . (strlen($value) > self::MAXIMUM_STRING_LENGTH ? substr($value, 0, self::MAXIMUM_STRING_LENGTH - 5) . '[...]' : $value) . '\'';
        } else {
            $normalizedValue = is_null($value) ? 'null' : gettype($value);
        }
        $message = sprintf('Invalid enum value. Expected %s, received %s', implode(' | ', self::normalizeArrayValues(self::enumOptions($schema))), $normalizedValue);
        $issue = new InvalidEnumValue($message, [], $normalizedValue, self::enumOptions($schema));
        return new self($value, $schema, Issues::create($issue));
    }

    public static function tooSmall(mixed $value, IntegerSchema|StringSchema|ListSchema $schema, int $min, bool $inclusive, bool $exact): self
    {
        $message = match ($schema::class) {
            IntegerSchema::class => sprintf('Number must be greater than or equal to %d', $min),
            StringSchema::class => sprintf('String must contain at least %d character(s)', $min),
            ListSchema::class => sprintf('Array must contain at least %d element(s)', $min),
        };
        $issue = new TooSmall($message, [], gettype($value), $min, $inclusive, $exact);
        return new self($value, $schema, Issues::create($issue));
    }

    public static function tooBig(mixed $value, IntegerSchema|StringSchema|ListSchema $schema, int $max, bool $inclusive, bool $exact): self
    {
        $message = match ($schema::class) {
            IntegerSchema::class => sprintf('Number must be less than or equal to %d', $max),
            StringSchema::class => sprintf('String must contain at most %d character(s)', $max),
            ListSchema::class => sprintf('Array must contain at most %d element(s)', $max),
        };
        $issue = new TooBig($message, [], gettype($value), $max, $inclusive, $exact);
        return new self($value, $schema, Issues::create($issue));
    }

    /**
     * @param array<string> $keys
     */
    public static function unrecognizedKeys(mixed $value, ShapeSchema $schema, array $keys): self
    {
        $message = sprintf('Unrecognized key(s) in object: %s', implode(', ', self::normalizeArrayValues($keys)));
        $issue = new UnrecognizedKeys($message, [], $keys);
        return new self($value, $schema, Issues::create($issue));
    }

    public static function required(Schema $schema, Schema $propertySchema): self
    {
        $issue = new InvalidType('Required', [], $propertySchema, 'undefined');
        return new self(null, $schema, Issues::create($issue));
    }

    public static function invalidPattern(string $value, StringSchema $schema): self
    {
        $issue = new InvalidString('Value does not match regular expression', [], 'regex');
        return new self($value, $schema, Issues::create($issue));
    }

    public static function invalidString(string $value, StringSchema $schema): self
    {
        Assert::notNull($schema->format);
        $format = $schema->format->name;
        $issue = new InvalidString('Invalid ' . $format, [], $format);
        return new self($value, $schema, Issues::create($issue));
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function custom(string $message, mixed $value, Schema $schema, array $params = []): self
    {
        $issue = new Custom($message, [], $params);
        return new self($value, $schema, Issues::create($issue));
    }

    public function jsonSerialize(): Issues
    {
        return $this->issues;
    }

    // ----------------------------------


    /**
     * @param array<string|int> $values
     * @return array<string|int>
     */
    private static function normalizeArrayValues(array $values): array
    {
        return array_map(static fn (int|string $value) => is_string($value) ? '\'' . $value . '\'' : $value, $values);
    }

    /**
     * @return array<string|int>
     */
    private static function enumOptions(EnumSchema $schema): array
    {
        return array_values(array_map(static fn(EnumCaseSchema $caseSchema) => $caseSchema->getValue(), $schema->caseSchemas));
    }
}
