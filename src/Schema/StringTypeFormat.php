<?php

declare(strict_types=1);

namespace Wwwision\Types\Schema;

/** @see https://json-schema.org/understanding-json-schema/reference/string.html#format */
enum StringTypeFormat
{
    case date;
    case date_time;
    case duration;
    case email;
    case hostname;
    case idn_email;
    //case idn_hostname;
    case ipv4;
    case ipv6;
//    case iri;
//    case iri_reference;
//    case json_pointer;
    case regex;
//    case relative_json_pointer;
    case time;
    case uri;
//    case uri_reference;
//    case uri_template;
    case uuid;
}
