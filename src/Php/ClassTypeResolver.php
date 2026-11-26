<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Php;

use Trongio\PhpTsTypes\Exceptions\UnsupportedType;
use Trongio\PhpTsTypes\Type\Reference;
use Trongio\PhpTsTypes\Type\Type;

/** Turns a class met inside a PHP type into a type, usually a {@see Reference}. */
interface ClassTypeResolver
{
    /**
     * @param  string  $where  `Class::$property.key`, for the error when the class is not allowed
     *
     * @throws UnsupportedType
     */
    public function resolveClass(string $class, string $where): Type;
}
