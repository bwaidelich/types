<?php

declare(strict_types=1);

namespace Wwwision\Types;

/**
 * @template T of object
 * @param class-string<T> $className
 * @return T
 */
function instantiate(string $className, mixed $input, Options|null $options = null): object
{
    return Parser::instantiate($className, $input, $options);
}
