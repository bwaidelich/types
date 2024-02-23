<?php

declare(strict_types=1);

namespace Wwwision\Types\Exception\Issues;

use JsonSerializable;

/**
 * CoerceException issue code (inspired from https://zod.dev/ERROR_HANDLING?id=zodissuecode)
 */
enum IssueCode implements JsonSerializable
{
    case invalid_type;
    case unrecognized_keys;
    //case invalid_union;
    case invalid_enum_value;
    //case invalid_arguments;
    case invalid_return_type;
    //case invalid_date;
    case invalid_string;
    case too_small;
    case too_big;
    //case not_multiple_of;
    case custom;

    public function jsonSerialize(): string
    {
        return $this->name;
    }
}
