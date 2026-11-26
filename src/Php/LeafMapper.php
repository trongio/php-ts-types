<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Php;

use Trongio\PhpTsTypes\Type\Type;

/**
 * A consumer's own class => type rule, consulted before enums and sources: e.g. `CarbonInterface` => `string`
 * for a project that serializes dates as ISO strings. Return null to pass.
 */
interface LeafMapper
{
    public function map(string $class): ?Type;
}
