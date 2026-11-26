<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Laravel;

use Illuminate\Support\ServiceProvider;

/** Registers `ts-types:generate` and the `ts-types` config. Console only: nothing runs on a web request. */
final class TsTypesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ts-types.php', 'ts-types');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([GenerateCommand::class]);
        $this->publishes([__DIR__.'/../../config/ts-types.php' => $this->app->configPath('ts-types.php')], 'ts-types-config');
    }
}
