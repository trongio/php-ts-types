<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Php;

use Trongio\PhpTsTypes\Type\Type;

/** Maps a class, or anything extending or implementing it, to a fixed type. */
final readonly class ClassMapLeafMapper implements LeafMapper
{
    /** @param  array<string, Type>  $map  class or interface => type */
    public function __construct(
        private array $map,
    ) {}

    public function map(string $class): ?Type
    {
        foreach ($this->map as $parent => $type) {
            if (is_a($class, $parent, true)) {
                return $type;
            }
        }

        return null;
    }
}
