<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

/** @phpstan-type Row array{id: int} */
final readonly class FixtureAliased extends Shape
{
    public function __construct(
        public int $id,
    ) {}
}
