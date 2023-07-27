<?php

declare(strict_types=1);

namespace Wwwision\Types;

/**
 * @template T of object
 * @param class-string<T> $className
 * @param mixed $input
 * @return T
 */
function instantiate(string $className, mixed $input): object
{
    return Parser::instantiate($className, $input);
}
