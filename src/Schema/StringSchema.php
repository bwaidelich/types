<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Stringable;
use Webmozart\Assert\Assert;

use function filter_var;
use function get_debug_type;
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
    ) {
    }

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

    public function instantiate(mixed $value): object
    {
        if (is_object($value) && $this->reflectionClass->isInstance($value)) {
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
            $value = (string)$value;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Value of type %s cannot be casted to string', get_debug_type($value)));
        }
        if ($this->minLength !== null && strlen($value) < $this->minLength) {
            throw new InvalidArgumentException(sprintf('Value "%s" does not have the required minimum length of %d characters', $value, $this->minLength));
        }
        if ($this->maxLength !== null && strlen($value) > $this->maxLength) {
            throw new InvalidArgumentException(sprintf('Value "%s" exceeds the allowed maximum length of %d characters', $value, $this->maxLength));
        }
        if ($this->pattern !== null && preg_match('/' . $this->pattern . '/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Value "%s" does not match the regular expression "/%s/"', $value, $this->pattern));
        }
        if ($this->format !== null) {
            $matchesFormat = match ($this->format) {
                StringTypeFormat::email => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                StringTypeFormat::uri => filter_var($value, FILTER_VALIDATE_URL) !== false,
                StringTypeFormat::date => ($d = DateTimeImmutable::createFromFormat('Y-m-d', $value)) && $d->format('Y-m-d') === $value,
                StringTypeFormat::date_time => ($d = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value)) && $d->format(DateTimeInterface::W3C) === $value,
                StringTypeFormat::uuid => Uuid::isValid($value),
            };
            if (!$matchesFormat) {
                throw new InvalidArgumentException(sprintf('Value "%s" does not match format "%s"', $value, $this->format->name));
            }
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
