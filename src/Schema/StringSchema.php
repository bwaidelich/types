<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use DateTimeImmutable;
use DateTimeInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Stringable;
use Webmozart\Assert\Assert;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\Issues\Issues;

use function filter_var;
use function is_string;
use function preg_match;
use function sprintf;
use function strlen;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;

final class StringSchema implements Schema
{
    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    public function __construct(
        private readonly ReflectionClass $reflectionClass,
        public readonly ?string $description,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly ?string $pattern = null,
        public readonly ?StringTypeFormat $format = null,
    ) {}

    public function getType(): string
    {
        return 'string';
    }

    public function getName(): string
    {
        return $this->reflectionClass->getShortName();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @phpstan-assert-if-true object $value */
    public function isInstance(mixed $value): bool
    {
        return is_object($value) && $this->reflectionClass->isInstance($value);
    }

    public function instantiate(mixed $value): object
    {
        if ($this->isInstance($value)) {
            return $value;
        }
        $stringValue = $this->coerce($value);
        $constructor = $this->reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $this->reflectionClass->getName()));
        try {
            $instance = $this->reflectionClass->newInstanceWithoutConstructor();
            $constructor->invoke($instance, $stringValue);
            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            throw new RuntimeException(sprintf('Failed to instantiate "%s": %s', $this->getName(), $e->getMessage()), 1688570532, $e);
        }
        // @codeCoverageIgnoreEnd
        return $instance;
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
                //StringTypeFormat::idn_hostname => throw new \Exception('To be implemented'),
                StringTypeFormat::ipv4 => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
                StringTypeFormat::ipv6 => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
                //StringTypeFormat::iri => throw new \Exception('To be implemented'),
                //StringTypeFormat::iri_reference => throw new \Exception('To be implemented'),
                //StringTypeFormat::json_pointer => throw new \Exception('To be implemented'),
                StringTypeFormat::regex => @preg_match('/' . $value . '/', '') !== false,
                //StringTypeFormat::relative_json_pointer => throw new \Exception('To be implemented'),
                StringTypeFormat::time => preg_match('/^([01]\d|2[0-3]):([0-5]\d):([0-5]\d|60)(\.\d+)?(Z|[+-]([01]\d|2[0-3]):?([0-5]\d)?)?$/i', $value) === 1,
                StringTypeFormat::uri => filter_var($value, FILTER_VALIDATE_URL) !== false,
                //StringTypeFormat::uri_reference => throw new \Exception('To be implemented'),
                //StringTypeFormat::uri_template => throw new \Exception('To be implemented'),
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
        return $result;
    }
}
