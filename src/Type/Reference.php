<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/** A reference to a named type defined in the same {@see TypeDefinitions}. The emitter refuses a dangling one. */
final readonly class Reference implements Type
{
    public function __construct(
        public string $name,
    ) {}

    public function key(): string
    {
        return 'ref:'.$this->name;
    }
}
