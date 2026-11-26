<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Fixtures {
    /** A bracketed namespace block: imports are per block, so a docblock name is not resolved here. */
    final readonly class FixtureBracketed extends Shape
    {
        /** @param  list<FixtureChild>  $children */
        public function __construct(
            public array $children,
        ) {}
    }
}
