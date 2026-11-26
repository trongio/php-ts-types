<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Exceptions;

/** A type refers to a name that is not defined in the same output. */
final class UnresolvedReference extends TypeGenerationException
{
    public function __construct(string $name, string $where)
    {
        parent::__construct("{$where}: reference to undefined type {$name}");
    }
}
