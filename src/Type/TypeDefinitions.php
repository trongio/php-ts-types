<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

use Trongio\PhpTsTypes\Exceptions\ConflictingTypeName;

/**
 * The named types of one output file, name => type. Defining a name twice with the SAME structure is a no-op:
 * two sources may reach one type, and two PHP classes with the same short name and an identical shape (two
 * `Tone` enums with the same values, say) share one TypeScript name. A different structure throws, naming
 * both origins (the FQCNs, for generated types), so two shapes never share a name silently.
 */
final class TypeDefinitions
{
    /** @var array<string, Type> */
    private array $types = [];

    /** @var array<string, string> name => who defined it, for the conflict message */
    private array $origins = [];

    public function define(string $name, Type $type, string $origin = ''): void
    {
        if (preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $name) !== 1) {
            throw new ConflictingTypeName($name, 'is not a valid TypeScript type name'.($origin === '' ? '' : ' (from '.$origin.')'));
        }

        $existing = $this->types[$name] ?? null;

        if ($existing !== null && ! Types::equal($existing, $type)) {
            throw new ConflictingTypeName($name, 'is claimed by two different shapes: '.$this->origins[$name].' and '.$origin);
        }

        $this->types[$name] ??= $type;
        $this->origins[$name] ??= $origin;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->types);
    }

    /** @return array<string, Type> sorted by name */
    public function all(): array
    {
        $types = $this->types;
        ksort($types, SORT_STRING);

        return $types;
    }
}
