<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception\Issues;

final class TooSmall implements Issue
{
    private IssueCode $code;

    /**
     * @param array<string|int> $path
     * @param string $type The type of the data failing validation
     * @param int|float $minimum The expected length/value
     * @param bool $inclusive Whether the minimum is included in the range of acceptable values
     * @param bool $exact Whether the size/length is constrained to be an exact value (used to produce more readable error messages)
     */
    public function __construct(
        private readonly string $message,
        private readonly array $path,
        public readonly string $type,
        public readonly int|float $minimum,
        public readonly bool $inclusive,
        public readonly bool $exact,
    ) {
        $this->code = IssueCode::too_small;
    }

    public function withPrependedPathSegment(string|int $pathSegment): self
    {
        return new self($this->message, [$pathSegment, ...$this->path], $this->type, $this->minimum, $this->inclusive, $this->exact);
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
