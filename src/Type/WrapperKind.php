<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/** The Inertia v3 lazy-prop kinds. Each is a different server behaviour; all of them may be absent on the page. */
enum WrapperKind: string
{
    case Deferred = 'deferred';
    case Optional = 'optional';
    case Once = 'once';
    case Merge = 'merge';
}
