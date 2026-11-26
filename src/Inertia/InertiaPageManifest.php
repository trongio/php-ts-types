<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Inertia;

use ReflectionClass;
use Trongio\PhpTsTypes\Attributes\InertiaPage;
use Trongio\PhpTsTypes\Exceptions\ConflictingTypeName;
use Trongio\PhpTsTypes\Exceptions\UnresolvedReference;
use Trongio\PhpTsTypes\Type\ObjectType;
use Trongio\PhpTsTypes\Type\Property;
use Trongio\PhpTsTypes\Type\Reference;
use Trongio\PhpTsTypes\Type\TypeDefinitions;
use Trongio\PhpTsTypes\TypeGenerator;

/**
 * Adds the page manifest to the definitions: `export type InertiaPages = { "settings/Notifications":
 * NotificationsProps; ... }`, one key per page component. A guard (or a typed `router`) reads a page's props
 * type from it. The component comes from {@see InertiaPage} when present, else from a class constant (`COMPONENT`
 * by default) the class declares itself; a class with neither is not a page and is skipped.
 */
final readonly class InertiaPageManifest
{
    public function __construct(
        private string $typeName = 'InertiaPages',
        private ?string $constant = 'COMPONENT',
    ) {}

    /** @param  list<class-string>  $classes */
    public function addTo(TypeDefinitions $definitions, TypeGenerator $generator, array $classes): void
    {
        $pages = [];

        foreach ($classes as $class) {
            $component = $this->component(new ReflectionClass($class));

            if ($component === null) {
                continue;
            }

            if (array_key_exists($component, $pages)) {
                throw new ConflictingTypeName($this->typeName, "repeats the page {$component}: {$class} and the class typed {$pages[$component]}");
            }

            $pages[$component] = $generator->nameOf($class) ?? throw new UnresolvedReference($class, $this->typeName);
        }

        ksort($pages, SORT_STRING);
        $properties = [];

        foreach ($pages as $component => $type) {
            $properties[] = new Property($component, new Reference($type));
        }

        $definitions->define($this->typeName, new ObjectType($properties), self::class);
    }

    /** @param  ReflectionClass<object>  $class */
    public function component(ReflectionClass $class): ?string
    {
        foreach ($class->getAttributes(InertiaPage::class) as $attribute) {
            return $attribute->newInstance()->component;
        }

        if ($this->constant === null || $class->isAbstract()) {
            return null;
        }

        $constant = $class->getReflectionConstant($this->constant);

        if ($constant === false || $constant->getDeclaringClass()->getName() !== $class->getName()) {
            return null;
        }

        $value = $constant->getValue();

        return is_string($value) && $value !== '' ? $value : null;
    }
}
