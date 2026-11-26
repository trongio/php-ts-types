<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

use Trongio\PhpTsTypes\Tests\Fixtures\FixtureLevel as Level;

/** The generator's happy path: one property per row of the mapping table that has a native type. */
final readonly class FixturePage extends Shape
{
    public const string COMPONENT = 'fixture/Page';

    /**
     * @param  list<FixtureChild>  $children  the rows
     * @param  array{a: int, 'b-c': Level}  $shape
     */
    public function __construct(
        public int $count,
        public ?float $ratio,
        public bool $on,
        public string|int $id,
        public FixtureTone $tone,
        public ?FixtureChild $child,
        public array $children,
        public array $shape,
    ) {}
}
