<?php

declare(strict_types=1);

namespace Wwwision\Types;

final class Options
{
    private function __construct(
        public readonly bool $ignoreUnrecognizedKeys,
    ) {}

    public static function create(
        bool|null $ignoreUnrecognizedKeys = null,
    ): self {
        return new self(
            $ignoreUnrecognizedKeys ?? false,
        );
    }
}
