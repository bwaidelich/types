<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception\Issues;

final class InvalidEnumValue implements Issue
{
    private IssueCode $code;

    /**
     * @param array<string|int> $path
     * @param array<string|int> $options The set of acceptable string/int values for this enum.
     */
    public function __construct(
        private readonly string $message,
        private readonly array $path,
        private readonly string $received,
        public readonly array $options,
    ) {
        $this->code = IssueCode::invalid_enum_value;
    }

    public function withPrependedPathSegment(string|int $pathSegment): self
    {
        return new self($this->message, [$pathSegment, ...$this->path], $this->received, $this->options);
    }

    public function code(): IssueCode
    {
        return $this->code;
    }

    public function path(): array
    {
        return $this->path;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
