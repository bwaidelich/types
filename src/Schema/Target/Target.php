<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema\Target;

/**
 * The "thing" a {@see \Wwwision\Types\Schema\Schema} represents and instantiates into.
 *
 * This is the single seam that lets one set of schema classes serve both modes:
 *  - {@see ClassTarget}   — backed by a real PHP class (the original behavior)
 *  - {@see DynamicTarget} — backed by nothing; instantiates to a generic container
 *
 * A schema keeps all of its (unchanged) coercion/validation logic and only delegates the three
 * class-coupled concerns — naming, instance checking, and the final "build the object" step —
 * to its Target.
 */
interface Target
{
    public function name(): string;

    public function isInstance(mixed $value): bool;

    /**
     * Builds the final value from already-coerced constructor arguments (positional or named).
     *
     * @param array<int|string, mixed> $arguments
     */
    public function construct(array $arguments): mixed;
}
