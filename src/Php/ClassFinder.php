<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Php;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** The classes, interfaces and enums of a PSR-4 directory, sorted. Files that declare none are skipped. */
final class ClassFinder
{
    /** @return list<class-string> */
    public static function find(string $directory, string $namespace): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $root = rtrim($directory, '/').'/';
        $classes = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root), -4);
            $class = rtrim($namespace, '\\').'\\'.str_replace('/', '\\', $relative);

            if (class_exists($class) || interface_exists($class) || enum_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }
}
