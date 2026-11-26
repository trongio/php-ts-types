<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/**
 * One key of an object type. The name is emitted verbatim. `optional` (`x?:`) and a nullable type (`T | null`)
 * are independent: a key can be either, both or neither. A {@see Wrapped} type also makes the key optional.
 */
final readonly class Property
{
    public function __construct(
        public string $name,
        public Type $type,
        public bool $optional = false,
        public string $description = '',
    ) {}

    public function isOptional(): bool
    {
        return $this->optional || $this->type instanceof Wrapped;
    }
}
