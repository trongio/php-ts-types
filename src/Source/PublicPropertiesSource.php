<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Source;

use ReflectionClass;
use Trongio\PhpTsTypes\Attributes\TsType;
use Trongio\PhpTsTypes\Exceptions\UnsupportedType;
use Trongio\PhpTsTypes\Php\PhpTypeMapper;
use Trongio\PhpTsTypes\Type\ObjectType;
use Trongio\PhpTsTypes\Type\Property;
use Trongio\PhpTsTypes\Type\Type;

/**
 * Describes a class as its public, non-static properties, in declaration order, each key spelled by
 * `$keys` (verbatim by default). It supports concrete classes extending or implementing one of `$bases`, and,
 * with `$attribute`, any class marked {@see TsType}. A non-public or static property is refused: the wire is
 * meant to be exactly what this type says, and a hidden member is how the two drift.
 */
final readonly class PublicPropertiesSource implements TypeSource
{
    /** @param  list<string>  $bases  base classes or interfaces whose concrete subclasses are collected */
    public function __construct(
        private array $bases = [],
        private bool $attribute = true,
        private KeyCase $keys = KeyCase::Verbatim,
    ) {}

    public function supports(ReflectionClass $class): bool
    {
        if ($class->isAbstract() || $class->isInterface() || $class->isEnum()) {
            return false;
        }

        if ($this->attribute && $class->getAttributes(TsType::class) !== []) {
            return true;
        }

        foreach ($this->bases as $base) {
            if ($class->isSubclassOf($base)) {
                return true;
            }
        }

        return false;
    }

    public function name(ReflectionClass $class): string
    {
        foreach ($class->getAttributes(TsType::class) as $attribute) {
            $name = $attribute->newInstance()->name;

            if ($name !== null) {
                return $name;
            }
        }

        return $class->getShortName();
    }

    public function describe(ReflectionClass $class, PhpTypeMapper $mapper): Type
    {
        $mapper->assertNoAliases($class);
        $properties = [];

        foreach ($class->getProperties() as $property) {
            if (! $property->isPublic() || $property->isStatic()) {
                throw new UnsupportedType(PhpTypeMapper::where($property), 'a non-public or static property');
            }

            $properties[] = new Property(
                $this->keys->apply($property->getName()),
                $mapper->property($property),
                description: $mapper->description($property),
            );
        }

        return new ObjectType($properties);
    }
}
