<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

use Trongio\PhpTsTypes\Attributes\InertiaPage;
use Trongio\PhpTsTypes\Attributes\TsType;

/** Collected by its attribute alone (no base class), under another name, and a page by #[InertiaPage]. */
#[TsType('Renamed')]
#[InertiaPage('fixture/Named')]
final readonly class FixtureNamed
{
    public function __construct(
        public string $snake_case_key,
    ) {}
}
