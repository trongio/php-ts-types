<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Source;

/** How {@see PublicPropertiesSource} spells a property name as a key. Verbatim unless the wire renames. */
enum KeyCase: string
{
    case Verbatim = 'verbatim';
    case Camel = 'camel';
    case Snake = 'snake';

    public function apply(string $name): string
    {
        return match ($this) {
            self::Verbatim => $name,
            self::Camel => lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)))),
            self::Snake => strtolower((string) preg_replace('/(?<=[a-z0-9])[A-Z]/', '_$0', $name)),
        };
    }
}
