<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

use Trongio\PhpTsTypes\Tests\Fixtures\FixtureChild as Kid;
use Trongio\PhpTsTypes\Tests\Fixtures\Other\FixtureTone as OtherTone;

/** Classes named through group imports, one of them aliased. */
final readonly class FixtureGroupUse extends Shape
{
    /** @param  list<Kid>  $kids */
    public function __construct(
        public array $kids,
        public FixtureLevel $level,
        public OtherTone $tone,
    ) {}
}
