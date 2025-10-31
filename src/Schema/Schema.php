<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

use JsonSerializable;
use Wwwision\Types\Options;

interface Schema extends JsonSerializable
{
    public function getType(): string;
    public function getName(): string;
    public function getDescription(): string|null;
    public function isInstance(mixed $value): bool;
    public function instantiate(mixed $value, Options $options): mixed;
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;
}
