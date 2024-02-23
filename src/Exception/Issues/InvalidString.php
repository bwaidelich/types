<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception\Issues;

final class InvalidString implements Issue
{
    private IssueCode $code;

    /**
     * @param array<string|int> $path
     */
    public function __construct(
        private readonly string $message,
        private readonly array $path,
        public readonly string $validation,
    ) {
        $this->code = IssueCode::invalid_string;
    }

    public function withPrependedPathSegment(string|int $pathSegment): self
    {
        return new self($this->message, [$pathSegment, ...$this->path], $this->validation);
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
