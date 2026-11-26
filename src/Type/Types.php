<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/** Helpers over the type model. */
final class Types
{
    /** Structural equality. */
    public static function equal(Type $a, Type $b): bool
    {
        return $a->key() === $b->key();
    }

    /** `T | null`. */
    public static function nullable(Type $type): Type
    {
        return Union::of($type, Primitive::null());
    }
}
