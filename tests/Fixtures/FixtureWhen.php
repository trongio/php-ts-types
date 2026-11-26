<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

use DateTimeImmutable;

/** For the leaf-mapper test: a date the consumer maps to string. */
final readonly class FixtureWhen extends Shape
{
    public function __construct(
        public DateTimeImmutable $at,
    ) {}
}
