<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

use InvalidArgumentException;

/** `{ a: T; b?: U }`: a JSON object with known keys. Key order is kept for output and ignored by equality. */
final readonly class ObjectType implements Type
{
    /** @param  list<Property>  $properties */
    public function __construct(
        public array $properties,
    ) {
        $names = array_map(static fn (Property $property): string => $property->name, $properties);

        if (count($names) !== count(array_unique($names))) {
            throw new InvalidArgumentException('An object type has a duplicate key: '.implode(', ', $names));
        }
    }

    public function key(): string
    {
        $keys = array_map(
            static fn (Property $p): string => json_encode($p->name, JSON_THROW_ON_ERROR).($p->isOptional() ? '?' : '').':'.$p->type->key(),
            $this->properties,
        );
        sort($keys);

        return 'object{'.implode(';', $keys).'}';
    }
}
