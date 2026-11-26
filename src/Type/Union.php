<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/**
 * `A | B | ...`, flattened and de-duplicated structurally, members in first-seen order. Build it with
 * {@see self::of()}, which returns the single member when only one is left.
 */
final readonly class Union implements Type
{
    /** @param  list<Type>  $members  two or more, none of them a Union */
    private function __construct(
        public array $members,
    ) {}

    public static function of(Type ...$types): Type
    {
        $members = [];

        foreach ($types as $type) {
            foreach ($type instanceof self ? $type->members : [$type] as $member) {
                $members[$member->key()] ??= $member;
            }
        }

        $members = array_values($members);

        return count($members) === 1 ? $members[0] : new self($members);
    }

    public function key(): string
    {
        $keys = array_map(static fn (Type $member): string => $member->key(), $this->members);
        sort($keys);

        return 'union('.implode('|', $keys).')';
    }
}
