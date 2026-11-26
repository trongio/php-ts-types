<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures\Other;

use Trongio\PhpTsTypes\Tests\Fixtures\Shape;

/** Same short name as Fixtures\FixtureChild, different shape: the two must not share a TypeScript name. */
final readonly class FixtureChild extends Shape
{
    public function __construct(
        public int $label,
    ) {}
}
