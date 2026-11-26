<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The core works without Laravel: Laravel is a suggested dependency, so only the bridge under src/Laravel may
 * name an Illuminate class or a Laravel helper. Anything else that did would fatal in a plain PHP project.
 */
final class CoreIndependenceTest extends TestCase
{
    public function test_only_the_laravel_bridge_references_laravel(): void
    {
        $src = dirname(__DIR__, 2).'/src';
        $offenders = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || str_starts_with($file->getPathname(), $src.'/Laravel/')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            if (preg_match('/\bIlluminate\\\\|\b(?:resource_path|app_path|config|app)\(/', $source) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($src) + 1);
            }
        }

        self::assertSame([], $offenders);
    }
}
