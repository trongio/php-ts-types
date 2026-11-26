<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

/** An array with no key type: list<> and array<string, T> are the only array types the table accepts. */
final readonly class FixtureBareArray extends Shape
{
    /** @param  array<mixed>  $items */
    public function __construct(
        public array $items,
    ) {}
}
