<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use Ramsey\Uuid\Uuid;
use Stringable;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\Issues\Issues;
use Wwwision\Types\Options;
use Wwwision\Types\Schema\Target\Target;

use function filter_var;
use function is_string;
use function preg_match;
use function strlen;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;

final class StringSchema implements Schema
{
    /**
     * @param array<string>|null $examples
     * @param array<string, mixed>|null $extensions
     */
    public function __construct(
        private readonly Target $target,
        public readonly string|null $description,
        public readonly int|null $minLength = null,
        public readonly int|null $maxLength = null,
        public readonly string|null $pattern = null,
        public readonly StringTypeFormat|null $format = null,
        public readonly array|null $examples = null,
        public readonly array|null $extensions = null,
    ) {}

    public function getType(): string
    {
        return 'string';
    }

    public function getName(): string
    {
        return $this->target->name();
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function isInstance(mixed $value): bool
    {
        return $this->target->isInstance($value);
    }

    public function instantiate(mixed $value, Options $options): mixed
    {
        if ($this->target->isInstance($value)) {
            return $value;
        }
        $stringValue = $this->coerce($value);
        return $this->target->construct($this, [$stringValue]);
    }

    private function coerce(mixed $value): string
    {
        if (is_int($value) || $value instanceof Stringable) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            throw CoerceException::invalidType($value, $this);
        }
        $issues = Issues::empty();
        if ($this->minLength !== null && strlen($value) < $this->minLength) {
            $issues = $issues->add(CoerceException::tooSmall($value, $this, $this->minLength, true, $this->minLength === $this->maxLength)->issues);
        }
        if ($this->maxLength !== null && strlen($value) > $this->maxLength) {
            $issues = $issues->add(CoerceException::tooBig($value, $this, $this->maxLength, true, $this->minLength === $this->maxLength)->issues);
        }
        if ($this->pattern !== null && preg_match('/' . $this->pattern . '/', $value) !== 1) {
            $issues = $issues->add(CoerceException::invalidPattern($value, $this)->issues);
        }
        if ($this->format !== null) {
            $matchesFormat = match ($this->format) {
                StringTypeFormat::date => preg_match('/^(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value) === 1,
                StringTypeFormat::date_time => preg_match('/^(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])T([01]\d|2[0-3]):([0-5]\d):([0-5]\d|60)(\.\d+)?(Z|[+-]([01]\d|2[0-3]):?([0-5]\d)?)?$/i', $value) === 1,
                StringTypeFormat::duration => preg_match('/^P(?!$)(\d+(?:\.\d+)?Y)?(\d+(?:\.\d+)?M)?(\d+(?:\.\d+)?W)?(\d+(?:\.\d+)?D)?(T(?=\d)(\d+(?:\.\d+)?H)?(\d+(?:\.\d+)?M)?(\d+(?:\.\d+)?S)?)?$/i', $value) === 1,
                StringTypeFormat::email => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                StringTypeFormat::hostname => preg_match('/^(?!\d+$)(?!-)[[:alnum:]-]{0,63}(?<!-)$/', $value) === 1,
                StringTypeFormat::idn_email => count(explode('@', $value, 3)) === 2,
                StringTypeFormat::ipv4 => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
                StringTypeFormat::ipv6 => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
                StringTypeFormat::regex => @preg_match('/' . $value . '/', '') !== false,
                StringTypeFormat::time => preg_match('/^([01]\d|2[0-3]):([0-5]\d):([0-5]\d|60)(\.\d+)?(Z|[+-]([01]\d|2[0-3]):?([0-5]\d)?)?$/i', $value) === 1,
                StringTypeFormat::uri => filter_var($value, FILTER_VALIDATE_URL) !== false,
                StringTypeFormat::uuid => Uuid::isValid($value),
            };
            if (!$matchesFormat) {
                $issues = $issues->add(CoerceException::invalidString($value, $this)->issues);
            }
        }
        if (!$issues->isEmpty()) {
            throw CoerceException::fromIssues($issues, $value, $this);
        }
        return $value;
    }

    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
        ];
        if ($this->minLength !== null) {
            $result['minLength'] = $this->minLength;
        }
        if ($this->maxLength !== null) {
            $result['maxLength'] = $this->maxLength;
        }
        if ($this->pattern !== null) {
            $result['pattern'] = $this->pattern;
        }
        if ($this->format !== null) {
            $result['format'] = $this->format->name;
        }
        if ($this->examples !== null) {
            $result['examples'] = $this->examples;
        }
        if ($this->extensions !== null) {
            $result += $this->extensions;
        }
        return $result;
    }
}
