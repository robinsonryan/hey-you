# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial package setup

### Changed
- **Constraint matrix widened.** `php` `^8.3` → `^8.2` (restores PHP 8.2 support that
  v1.2.0 dropped; Laravel 11/12 still support it), dev matrix now allows
  `pestphp/pest` and `pestphp/pest-plugin-laravel` `^3|^4|^5` and pins
  `phpunit/phpunit` to `^11|^12|^13`. Laravel 11/12/13 and testbench 9/10/11 support
  is unchanged.
- **`ConfiguresIdentifiers` now sets `$incrementing = true`** (with
  `$keyType = 'string'`). In Eloquent that flag means "the database assigns the key on
  insert", which is what a `uuidv7()` column default does; it makes the INSERT compile
  with PostgreSQL's `returning "id"` so the generated UUID is hydrated onto the model.
  Previously every model came back from `create()` with a **null key**.
- Test suite runs against a real PostgreSQL 18 database instead of in-memory SQLite —
  SQLite cannot express a `uuidv7()` column default, so the suite could not migrate.
- `Uuid7Generator::generate()` return type narrowed `string|int` → `string`;
  `AutoIncrementGenerator::generate()` narrowed to `int`. The
  `IdentifierGenerator` contract is unchanged.
- `ResolverConstraints::$excludeContactPointIds` documented as `list<string>` (UUIDs),
  not `list<int>`.
- **Rector is now part of the quality gate.** `composer quality` runs
  `lint:check → analyze → refactor:check → test`; `refactor:check` was previously
  defined but never gated, which is how a 61-file backlog accumulated unseen.
  `phpstan.neon` gained `phpVersion: 80200` (matching the `php: ^8.2` floor, so the
  analyser cannot silently accept 8.3+ syntax) and `tmpDir: .phpstan.cache`.
- **Nine classes are now `readonly` classes** rather than plain `final` classes with
  all-`readonly` properties: `DefaultContactResolver`, `ResolverConstraints`,
  `ResolverExplanation`, `ResolverMatch`, `ResolverRequest`, `ResolverResult`,
  `ConsentResult`, `DncResult` and `GenericRegistryItem`. Every property was already
  `readonly`, so no assignment that used to work stops working. The one new restriction
  is that dynamic property creation on these objects is now a fatal `Error` instead of
  an 8.2 deprecation. All nine are `final`, so the "a readonly class can only be
  extended by a readonly class" rule is moot.
- `DefaultNormalizerRegistry::$default` is now `readonly` (it was only ever assigned in
  the constructor).
- Null guards on five call sites now assert the positive type
  (`$rule instanceof DoNotContact`) instead of `!== null`. Equivalent in every case —
  each guard reads a private method with a `?ConcreteClass` return type.
- `PhoneNormalizer::normalize()` dropped a redundant branch: the "11 digits starting
  with 1" case returned `'+'.$cleaned`, byte-for-byte identical to the fallthrough
  directly below it. Output is unchanged for every input.

### Fixed
- `phpstan.neon` no longer sets `checkMissingIterableValueType`, removed in PHPStan 2.
