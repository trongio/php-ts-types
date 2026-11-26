# trongio/php-ts-types

Generate TypeScript types from PHP classes, and fail CI when the committed file is stale.

- **Core** (no Laravel): a small structural type model (`Primitive`, `Literal`, `Union`, `ListType`,
  `RecordType`, `ObjectType` with optional keys, `Reference`, `Wrapped` for lazy props), `TypeDefinitions`
  (name => type, de-duplicated structurally, a different shape under a taken name is an error) and
  `TypeScriptEmitter` (one module, `export type` only, sorted, a "generated, do not edit" header, an
  optional raw `append` block). `Writer` writes the file, or checks it and returns a diff head.
- **Sources**: a `TypeSource` decides which classes become named types and what their wire shape is.
  `PublicPropertiesSource` ships: public properties of concrete subclasses of configured bases, or of
  `#[TsType]` classes, keys verbatim (or `camel`/`snake`). A second source (for example one that reads a
  `toArray()` return type) implements the same interface and passes type strings to
  `TypeGenerator::docType()`.
- **Laravel** (optional): `php artisan ts-types:generate [--check] [--output=path]`, configured by
  `config/ts-types.php`. Everything under `src/Laravel` needs `illuminate/console` and `illuminate/support`,
  which are only suggested: the core and the sources depend on nothing but `phpstan/phpdoc-parser`, and a
  test keeps every `Illuminate` reference inside `src/Laravel`.
- **Inertia**: `InertiaPageManifest` adds `export type InertiaPages = { "settings/Notifications":
  NotificationsProps; ... }`. A page's component comes from `#[InertiaPage('...')]`, else from a class
  constant (`COMPONENT` by default). `Testing\ShapeRules` gives an app architecture test.

## Allowed types

| PHP (native or PHPDoc)                 | TypeScript                 |
|----------------------------------------|----------------------------|
| `int`, `float`, int refinements        | `number`                   |
| `string`, string refinements           | `string`                   |
| `bool`, `true`, `false`, `null`        | `boolean`, `true`, `false`, `null` |
| `'a'`, `2` literal types               | `"a"`, `2`                 |
| `?T`, `A\|B`                           | `T \| null`, `A \| B`      |
| a backed enum                          | a named literal union      |
| a class a source supports              | a named reference          |
| a class a leaf mapper maps             | the mapped type            |
| `list<T>`                              | `T[]`                      |
| `array<string, T>`                     | `Record<string, T>`        |
| `array{a: T, 'b'?: U}` (sealed)        | `{ a: T; b?: U }`          |

Anything else throws, naming `Class::$property.key`: `mixed`, `object`, bare `array`, `T[]`,
`array<int, T>`, unsealed or positional shapes, `self::CONST` types, `@phpstan-type` aliases, unit enums and
unmapped classes (dates included: map them with a leaf mapper or send strings). It never emits `any` or
`unknown`.

Type names are the PHP short names (or `#[TsType(name)]`) in one flat module. Two classes with the same
short name and an identical shape share one TypeScript type; with different shapes generation fails and
names both FQCNs. Docblock names resolve through the file's `use` imports, group imports included; a file
with a bracketed `namespace X { }` block is refused.

## Install

```bash
composer require --dev trongio/php-ts-types
php artisan vendor:publish --tag=ts-types-config
```

Use it as a dev dependency and commit the generated file: production needs neither.

## Configure

```php
// config/ts-types.php
return [
    'output' => resource_path('js/types/generated/types.d.ts'),
    'discover' => [app_path('Inertia') => 'App\\Inertia'],
    'public_properties' => ['bases' => ['App\\Inertia\\PageProps'], 'attribute' => true, 'keys' => 'verbatim'],
    'leaves' => ['Carbon\\CarbonInterface' => 'string'],
    'inertia' => ['manifest' => true, 'type' => 'InertiaPages', 'constant' => 'COMPONENT'],
    'append' => '',
];
```

Keep the config to strings and arrays so `config:cache` works where the package is not installed.

## Generate and check

```bash
php artisan ts-types:generate          # write
php artisan ts-types:generate --check  # exit 1 and print the diff head when stale (CI)
```

## Without Laravel

```php
$generator = new TypeGenerator([new PublicPropertiesSource([MyBase::class])]);
$definitions = $generator->generate($classes);
$definitions->define('Extra', new ObjectType([new Property('id', Primitive::number())]));
echo (new TypeScriptEmitter)->emit($definitions);
```

## Tests

```bash
composer install && vendor/bin/phpunit
```

MIT licensed.
