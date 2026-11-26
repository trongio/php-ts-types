<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

use DateTimeImmutable;

final readonly class FixtureCarbon extends Shape
{
    public function __construct(
        public DateTimeImmutable $at,
    ) {}
}
