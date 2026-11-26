<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Exceptions;

/** A PHP type outside the mapping table, named by where it was met: `Class::$property.key`. */
final class UnsupportedType extends TypeGenerationException
{
    public function __construct(string $where, string $type)
    {
        parent::__construct("{$where}: unsupported type `{$type}`");
    }
}
