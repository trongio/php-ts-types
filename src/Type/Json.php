<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/**
 * Any JSON value, for a leaf the app sends exactly as it was stored (a recorded payload). Not `unknown`: the
 * generator defines it once as the named recursive type {@see self::NAME},
 * `string | number | boolean | null | JsonValue[] | { [key: string]: JsonValue }`, and every use refers to it.
 * Reach it through a leaf mapping (`'leaves' => [MyJson::class => 'json']`).
 */
final readonly class Json implements Type
{
    public const string NAME = 'JsonValue';

    public function key(): string
    {
        return 'json';
    }
}
