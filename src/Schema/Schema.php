<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use JsonSerializable;

/**
 * @template T of bool|int|object|string|null
 */
interface Schema extends JsonSerializable
{
    public function getType(): string;
    public function getName(): string;
    public function getDescription(): ?string;
    /**
     * @return T
     */
    public function instantiate(mixed $value): mixed;
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;
}
