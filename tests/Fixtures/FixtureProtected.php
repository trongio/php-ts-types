<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

final readonly class FixtureProtected extends Shape
{
    public function __construct(
        public int $id,
        protected string $secret,
    ) {}
}
