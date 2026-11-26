<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

enum FixtureTone: string
{
    case Info = 'info';
    case Warn = 'warn';
}
