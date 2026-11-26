<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Source;

use ReflectionClass;
use Trongio\PhpTsTypes\Php\PhpTypeMapper;
use Trongio\PhpTsTypes\Type\Type;

/**
 * Decides which classes become named types and what their wire shape is. The generator asks each source in
 * order; the first that supports a class describes it. {@see PublicPropertiesSource} reads public properties;
 * a second source could read a `toArray()` return type (for instance from a PHPStan collector's output) and
 * hand the type string to {@see PhpTypeMapper::docType()}.
 */
interface TypeSource
{
    /** @param  ReflectionClass<object>  $class */
    public function supports(ReflectionClass $class): bool;

    /**
     * The TypeScript name of a supported class.
     *
     * @param  ReflectionClass<object>  $class
     */
    public function name(ReflectionClass $class): string;

    /**
     * The wire shape of a supported class. Classes met inside it go back through the generator.
     *
     * @param  ReflectionClass<object>  $class
     */
    public function describe(ReflectionClass $class, PhpTypeMapper $mapper): Type;
}
