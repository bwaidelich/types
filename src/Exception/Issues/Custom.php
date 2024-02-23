<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception\Issues;

final class Custom implements Issue
{
    private IssueCode $code;

    /**
     * @param array<string|int> $path
     * @param array<mixed> $params
     */
    public function __construct(
        private readonly string $message,
        private readonly array $path,
        public readonly array $params,
    ) {
        $this->code = IssueCode::custom;
    }

    public function withPrependedPathSegment(string|int $pathSegment): self
    {
        return new self($this->message, [$pathSegment, ...$this->path], $this->params);
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
