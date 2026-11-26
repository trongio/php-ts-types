<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures\Other;

/** Same short name AND the same values as Fixtures\FixtureTone: one TypeScript type serves both. */
enum FixtureTone: string
{
    case Info = 'info';
    case Warn = 'warn';
}
