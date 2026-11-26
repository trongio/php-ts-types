<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Attributes;

use Attribute;

/**
 * Marks a class (or backed enum) for a source to collect, optionally under another TypeScript name. Sources
 * decide whether they honour it; the core never reads it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TsType
{
    public function __construct(
        public ?string $name = null,
    ) {}
}
