<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Exceptions;

use RuntimeException;

/** Base of every generation failure. Developer-facing: it stops a generate or check run. */
class TypeGenerationException extends RuntimeException {}
