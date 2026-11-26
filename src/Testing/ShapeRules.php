<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Testing;

use ReflectionClass;
use Trongio\PhpTsTypes\Source\PublicPropertiesSource;

/**
 * Reusable architecture rules for classes described by {@see PublicPropertiesSource}:
 * an app test asserts `ShapeRules::violations($classes) === []`. Each rule keeps the generated type honest:
 * final + readonly so no subclass or mutation changes the wire, public promoted properties so the wire is
 * exactly the constructor, no `@phpstan-type` aliases or `self::` constant types (not resolved), and no
 * override of the methods that emit the wire (`$emitters`, declared once on a base class).
 */
final class ShapeRules
{
    /**
     * @param  list<class-string>  $classes
     * @param  array<string, list<string>>  $emitters  base class => methods only it may declare
     * @return list<string> one line per violation, empty when every class complies
     */
    public static function violations(array $classes, array $emitters = []): array
    {
        $violations = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);

            if (! $reflection->isFinal()) {
                $violations[] = "{$class} must be final";
            }

            if (! $reflection->isReadOnly()) {
                $violations[] = "{$class} must be readonly";
            }

            $docs = [(string) $reflection->getDocComment(), (string) $reflection->getConstructor()?->getDocComment()];

            foreach ($reflection->getProperties() as $property) {
                $where = $class.'::$'.$property->getName();
                $docs[] = (string) $property->getDocComment();

                if (! $property->isPublic() || $property->isStatic() || ! $property->isPromoted()) {
                    $violations[] = "{$where} must be a public, non-static, constructor-promoted property";
                }
            }

            foreach ($docs as $doc) {
                if (preg_match('/@(phpstan|psalm)-(import-)?type\b|\b(self|static)::/', $doc, $match) === 1) {
                    $violations[] = "{$class} docblock uses {$match[0]}: use a named class or a backed enum";
                }
            }

            foreach ($emitters as $base => $methods) {
                if (! $reflection->isSubclassOf($base)) {
                    continue;
                }

                foreach ($methods as $method) {
                    if ($reflection->hasMethod($method) && $reflection->getMethod($method)->getDeclaringClass()->getName() !== $base) {
                        $violations[] = "{$class} overrides {$method}(): the wire must be the properties as declared";
                    }
                }
            }
        }

        return $violations;
    }
}
