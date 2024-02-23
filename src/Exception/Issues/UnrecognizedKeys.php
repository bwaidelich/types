<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception\Issues;

final class UnrecognizedKeys implements Issue
{
    private IssueCode $code;

    /**
     * @param array<string|int> $path
     * @param array<string> $keys The list of unrecognized keys
     */
    public function __construct(
        private readonly string $message,
        private readonly array $path,
        public readonly array $keys,
    ) {
        $this->code = IssueCode::unrecognized_keys;
    }

    public function withPrependedPathSegment(string|int $pathSegment): self
    {
        return new self($this->message, [$pathSegment, ...$this->path], $this->keys);
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
