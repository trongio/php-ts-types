<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/**
 * One node of the type model. Types compare structurally: two types are the same type when their `key()`s are
 * equal, whatever objects built them ({@see Types::equal()}). Descriptions never take part in the key.
 */
interface Type
{
    /** A canonical, structural identity: equal keys mean the same TypeScript type. */
    public function key(): string;
}
