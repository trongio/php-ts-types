<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

final readonly class FixtureChild extends Shape
{
    public function __construct(
        public string $label,
    ) {}
}
