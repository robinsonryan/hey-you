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

### Fixed
- `phpstan.neon` no longer sets `checkMissingIterableValueType`, removed in PHPStan 2.
