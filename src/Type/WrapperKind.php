<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/**
 * The ways a property may be missing from the page. The Inertia v3 lazy kinds (deferred, optional, once, merge) are
 * resolved later or on request; `gated` is sent eagerly with the page but left out entirely when it does not apply
 * (for example a block the viewer lacks the permission for). Each is a different server behaviour; all of them may
 * be absent on the page, so all emit an optional key.
 */
enum WrapperKind: string
{
    case Deferred = 'deferred';
    case Optional = 'optional';
    case Once = 'once';
    case Merge = 'merge';
    case Gated = 'gated';
}
