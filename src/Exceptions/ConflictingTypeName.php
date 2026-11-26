<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Exceptions;

/** Two different shapes claimed one type name, or a name is not a valid TypeScript identifier. */
final class ConflictingTypeName extends TypeGenerationException
{
    public function __construct(string $name, string $reason)
    {
        parent::__construct("Type name {$name} {$reason}");
    }
}
