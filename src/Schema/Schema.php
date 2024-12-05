<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use JsonSerializable;

interface Schema extends JsonSerializable
{
    public function getType(): string;
    public function getName(): string;
    public function getDescription(): ?string;
    public function isInstance(mixed $value): bool;
    public function instantiate(mixed $value): mixed;
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;
}
