<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception\Issues;

use Wwwision\Types\Schema\Schema;

final class InvalidType implements Issue
{
    private IssueCode $code;

    /**
     * @param array<string|int> $path
     */
    public function __construct(
        private readonly string $message,
        private readonly array $path,
        public readonly Schema $expected,
        public readonly string $received,
    ) {
        $this->code = IssueCode::invalid_type;
    }

    public function withPrependedPathSegment(string|int $pathSegment): self
    {
        return new self($this->message, [$pathSegment, ...$this->path], $this->expected, $this->received);
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
        $data = get_object_vars($this);
        $data['expected'] = $this->expected->getType();
        return $data;
    }
}
