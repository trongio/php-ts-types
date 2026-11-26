<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/** A string, int or boolean literal: `"info"`, `2`, `true`. A union of them is a literal union. */
final readonly class Literal implements Type
{
    public function __construct(
        public string|int|bool $value,
    ) {}

    public function key(): string
    {
        return 'literal:'.get_debug_type($this->value).':'.var_export($this->value, true);
    }
}
