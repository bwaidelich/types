<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception\Issues;

use JsonSerializable;

/**
 * CoerceException issue (inspired by https://zod.dev/ERROR_HANDLING?id=zodissue)
 */
interface Issue extends JsonSerializable
{
    public function code(): IssueCode;

    /**
     * @return array<string|int>
     */
    public function path(): array;

    public function message(): string;

    public function withPrependedPathSegment(string|int $pathSegment): self;
}
