<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/** `T[]`: a JSON array (a PHP `list<T>`). */
final readonly class ListType implements Type
{
    public function __construct(
        public Type $item,
    ) {}

    public function key(): string
    {
        return 'list<'.$this->item->key().'>';
    }
}
