<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/** `string`, `number`, `boolean` or `null`. */
final readonly class Primitive implements Type
{
    private function __construct(
        public string $name,
    ) {}

    public static function string(): self
    {
        return new self('string');
    }

    public static function number(): self
    {
        return new self('number');
    }

    public static function boolean(): self
    {
        return new self('boolean');
    }

    public static function null(): self
    {
        return new self('null');
    }

    /** The primitive named `string`, `number`, `boolean` or `null`, or null for any other name. */
    public static function named(string $name): ?self
    {
        return in_array($name, ['string', 'number', 'boolean', 'null'], true) ? new self($name) : null;
    }

    public function key(): string
    {
        return $this->name;
    }
}
