<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures;

/** A page with lazy props: a wrapper, a nullable wrapper (a key left out), both emitted as `key?: T`. */
final readonly class FixtureLazyPage extends Shape
{
    /**
     * @param  FixtureLazy<FixtureChild>  $child  sent only when asked for
     * @param  FixtureLazy<list<string>>|null  $names
     * @param  FixtureLazy<int>  $count
     */
    public function __construct(
        public FixtureLazy $child,
        public ?FixtureLazy $names,
        public ?FixtureLazy $count,
        public string $title,
    ) {}
}
