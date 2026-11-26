<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Attributes;

use Attribute;
use Trongio\PhpTsTypes\Inertia\InertiaPageManifest;

/**
 * Names the Inertia page component a props class renders (`settings/Notifications`), for the page manifest.
 * The alternative is a class constant ({@see InertiaPageManifest}).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class InertiaPage
{
    public function __construct(
        public string $component,
    ) {}
}
