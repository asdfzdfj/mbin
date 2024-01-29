<?php

declare(strict_types=1);

namespace App\Utils;

class ArrayTool
{
    /** wrap given object in an array if it wasn't already one */
    public static function wrapList(mixed $object): array
    {
        return self::isList($object) ? $object : [$object];
    }

    /** check if given object is an array and whether it is list/sequential array */
    public static function isList(mixed $object): bool
    {
        return \is_array($object) && array_is_list($object);
    }

    /** check if given object is an array and whether it is map/associative array */
    public static function isMap(mixed $object): bool
    {
        return \is_array($object) && !array_is_list($object);
    }

    /** unwrap list array and return first item if possible, otherwise return itself */
    public static function unwrapFirst(mixed $object): mixed
    {
        return self::isList($object) ? ($object[0] ?? null) : $object;
    }
}
