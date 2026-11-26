<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Laravel;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Trongio\PhpTsTypes\Emitter\TypeScriptEmitter;
use Trongio\PhpTsTypes\Emitter\Writer;
use Trongio\PhpTsTypes\Exceptions\TypeGenerationException;
use Trongio\PhpTsTypes\Inertia\InertiaPageManifest;
use Trongio\PhpTsTypes\Php\ClassFinder;
use Trongio\PhpTsTypes\Php\ClassMapLeafMapper;
use Trongio\PhpTsTypes\Php\LeafMapper;
use Trongio\PhpTsTypes\Source\KeyCase;
use Trongio\PhpTsTypes\Source\PublicPropertiesSource;
use Trongio\PhpTsTypes\Source\TypeSource;
use Trongio\PhpTsTypes\Type\Types;
use Trongio\PhpTsTypes\Type\WrapperKind;
use Trongio\PhpTsTypes\TypeGenerator;
use UnexpectedValueException;

/**
 * `php artisan ts-types:generate` writes the configured file; `--check` builds it in memory, compares, prints
 * the head of the diff and exits 1 on drift. Needs no database. Its output is developer console text.
 */
final class GenerateCommand extends Command
{
    protected $signature = 'ts-types:generate
        {--check : Compare the committed file with a fresh build; exit 1 on drift, write nothing}
        {--output= : Write or check this file instead of the configured one}';

    protected $description = 'Generate TypeScript types from PHP classes (trongio/php-ts-types).';

    public function handle(Repository $config): int
    {
        $option = $this->option('output');
        $configured = $config->get('ts-types.output');
        $path = match (true) {
            is_string($option) && $option !== '' => $option,
            is_string($configured) && $configured !== '' => $configured,
            default => $this->laravel->resourcePath('js/types/generated/types.d.ts'),
        };

        try {
            $contents = $this->build($config);
        } catch (TypeGenerationException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        // `temp_directory` (optional): where `--check` builds its throwaway copy; the system temp dir by default.
        $tempDirectory = $config->get('ts-types.temp_directory');
        $writer = new Writer(is_string($tempDirectory) && $tempDirectory !== '' ? $tempDirectory : null);

        if ($this->option('check') !== true) {
            $writer->write($path, $contents);
            $this->line('Wrote '.$path);

            return self::SUCCESS;
        }

        $diff = $writer->check($path, $contents);

        if ($diff === null) {
            $this->info('TypeScript types are up to date: '.$path);

            return self::SUCCESS;
        }

        $this->error('Stale: '.$path.'. Run `php artisan ts-types:generate` and commit the result.');
        $this->line($diff);

        return self::FAILURE;
    }

    private function build(Repository $config): string
    {
        $classes = self::strings($config->get('ts-types.classes', []));

        foreach (self::map($config->get('ts-types.discover', [])) as $directory => $namespace) {
            array_push($classes, ...ClassFinder::find($directory, $namespace));
        }

        /** @var list<class-string> $classes */
        $classes = array_values(array_unique($classes));
        sort($classes);

        $generator = new TypeGenerator($this->sources($config), $this->leaves($config), wrappers: self::wrappers($config));
        $definitions = $generator->generate($classes);

        if ($config->get('ts-types.inertia.manifest') === true) {
            $constant = $config->get('ts-types.inertia.constant');
            $type = $config->get('ts-types.inertia.type');
            (new InertiaPageManifest(is_string($type) ? $type : 'InertiaPages', is_string($constant) ? $constant : null))
                ->addTo($definitions, $generator, $classes);
        }

        $header = $config->get('ts-types.header');
        $append = $config->get('ts-types.append');

        return (new TypeScriptEmitter)->emit(
            $definitions,
            is_string($header) ? $header : TypeScriptEmitter::DEFAULT_HEADER,
            is_string($append) ? $append : '',
        );
    }

    /** @return list<TypeSource> */
    private function sources(Repository $config): array
    {
        $keys = $config->get('ts-types.public_properties.keys', 'verbatim');
        $sources = [new PublicPropertiesSource(
            self::strings($config->get('ts-types.public_properties.bases', [])),
            $config->get('ts-types.public_properties.attribute', true) !== false,
            KeyCase::tryFrom(is_string($keys) ? $keys : '') ?? throw new UnexpectedValueException('ts-types.public_properties.keys'),
        )];

        foreach (self::strings($config->get('ts-types.sources', [])) as $class) {
            $source = $this->laravel->make($class);
            $sources[] = $source instanceof TypeSource ? $source : throw new UnexpectedValueException($class.' is not a TypeSource');
        }

        return $sources;
    }

    /** @return list<LeafMapper> */
    private function leaves(Repository $config): array
    {
        $leaves = [];

        foreach (self::strings($config->get('ts-types.leaf_mappers', [])) as $class) {
            $leaf = $this->laravel->make($class);
            $leaves[] = $leaf instanceof LeafMapper ? $leaf : throw new UnexpectedValueException($class.' is not a LeafMapper');
        }

        $map = [];

        foreach (self::map($config->get('ts-types.leaves', [])) as $class => $primitive) {
            $map[$class] = Types::named($primitive) ?? throw new UnexpectedValueException("ts-types.leaves.{$class}");
        }

        if ($map !== []) {
            $leaves[] = new ClassMapLeafMapper($map);
        }

        return $leaves;
    }

    /**
     * `ts-types.wrappers`: lazy-prop wrapper class => `deferred`, `optional`, `once` or `merge`.
     *
     * @return array<string, WrapperKind>
     */
    private static function wrappers(Repository $config): array
    {
        $wrappers = [];

        foreach (self::map($config->get('ts-types.wrappers', [])) as $class => $kind) {
            $wrappers[ltrim($class, '\\')] = WrapperKind::tryFrom($kind) ?? throw new UnexpectedValueException("ts-types.wrappers.{$class}");
        }

        return $wrappers;
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /** @return array<string, string> */
    private static function map(mixed $value): array
    {
        $map = [];

        foreach (is_array($value) ? $value : [] as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }
}
