<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/**
 * A prop the server may leave out (Inertia's deferred, optional, once and merge props). Valid only as a
 * property's type; it emits the inner type and makes the key optional (`x?: T`). The kind is kept so a later
 * emitter can say more than `?:`.
 */
final readonly class Wrapped implements Type
{
    public function __construct(
        public WrapperKind $kind,
        public Type $inner,
    ) {}

    public function key(): string
    {
        return $this->kind->value.'<'.$this->inner->key().'>';
    }
}
