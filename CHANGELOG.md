# Changelog

## 0.3.0 - 2026-11-26

- A `gated` wrapper kind: a property sent eagerly with the page but left out entirely when it does not apply (e.g. a block the viewer lacks the permission for). Emits an optional key like the lazy kinds.

## 0.2.0 - 2026-11-26

- Lazy-prop wrappers: configure `wrappers` (class => kind: deferred, optional, once, merge) and a property typed `W<T>` or `?W<T>` emits an optional key `key?: T`. A wrapper without its type argument, inside a list, map, shape or another wrapper, or in a union with anything but null is refused.
- A `json` leaf: map a class to `Types::named('json')` to emit one recursive `JsonValue` type (string, number, boolean, null, arrays and string-keyed objects of it), with no `any` or `unknown`.

## 0.1.0 - 2026-11-26

First release: the structural type model, the deterministic TypeScript emitter with a `--check` drift mode, `PublicPropertiesSource` (reflection + phpstan/phpdoc-parser), marker attributes, and the optional Laravel bridge (`php artisan ts-types:generate [--check]`) with an Inertia page manifest.
