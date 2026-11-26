<?php

declare(strict_types=1);

// The package's own vendor/. A host project developing this package in place (a monorepo path package
// without its own vendor/) can point PHP_TS_TYPES_HOST_AUTOLOAD at its autoloader; this source tree is then
// registered first so the code being edited wins over any copy in the host's vendor/.
$own = __DIR__.'/../vendor/autoload.php';
$override = getenv('PHP_TS_TYPES_HOST_AUTOLOAD');

require is_string($override) && $override !== '' ? $override : $own;

spl_autoload_register(static function (string $class): void {
    foreach (['Trongio\\PhpTsTypes\\Tests\\' => __DIR__.'/', 'Trongio\\PhpTsTypes\\' => __DIR__.'/../src/'] as $prefix => $root) {
        if (str_starts_with($class, $prefix)) {
            $file = $root.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
}, true, true);
