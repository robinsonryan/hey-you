# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Finishes three features that were scaffolded and never wired: config keys and
documented APIs that pointed at machinery nothing connected. As of this work no event
class in the package lacks a dispatch site, and no config key is read by nothing —
both verified mechanically.

### Added
- **`Contactable` gains `contactPoints()` and `addresses()`.** `docs/spec.md` §3.2 has
  promised both since the package was designed, and the guides showed them in use —
  `$user->contactPoints`, `$company->addresses()->where('purpose', 'billing')->get()`,
  `Company::with('addresses')`. Neither existed; every one of those calls threw
  `BadMethodCallException`. Both are now `HasManyThrough` relations hopping the
  consumer's party, so they consume `Party::contactPoints()` / `Party::addresses()`
  rather than opening a second query path.

  The morph-type constraint lives inside `PartyHasManyThrough::addConstraints()`, not
  at the call site: two integer-keyed consumer types both start at id 1, so their
  `partyable_id` values collide and without the constraint one consumer reads the
  other's rows. Putting it in the relation makes the guarantee structural instead of
  a thing each caller has to remember. Proven by a test that forces two different
  bigint-keyed consumers onto the same id and asserts the collision before asserting
  isolation; deleting the constraint turns five tests red, one of them a clean
  cross-tenant read.
- **Verification history is written.** The `heyyou_verification_events` table, the
  `VerificationEvent` model and `ContactPoint::verificationEvents()` have existed since
  the schema was built, and nothing ever wrote a row — the config key said
  `log_history => true` and no code read it. `ContactPoint` gains four intent methods:
  `startVerification()`, `markVerified()`, `markVerificationFailed()` and
  `markVerificationExpired()`. Each moves the model, dispatches its event and writes
  the row as one act.

  Explicit methods rather than model hooks because a verification *failure* is not an
  attribute change, so no `updated` hook can observe one — which is exactly why
  `ContactPointVerificationFailed` and `ContactPointVerificationExpired` had sat in the
  package as the only two event classes with no dispatch site anywhere.

  Assigning `is_verified` directly still works and still dispatches
  `ContactPointVerified`, and now also records history. `verification.log_history` and
  `verification.default_expiration_days` both genuinely do something for the first
  time; when logging is off, events still dispatch — signalling is never gated by the
  history switch.

### Fixed
- **`has('party')` and `whereHas('party')` no longer throw for UUID7-keyed consumers.**
  v0.1.2 guarded `PartyMorphOne`'s column cast behind `getKeyType() !== 'string'`, on
  the reasoning that a UUID consumer's key was already a string and needed no help.
  It isn't the same question. Eloquent reports `getKeyType() === 'string'` for a native
  PostgreSQL `uuid` column exactly as it does for a `varchar`, and `uuid` has no
  implicit cast to `character varying` either — so
  `User::query()->has('party')->count()` failed with
  `SQLSTATE[42883] operator does not exist: uuid = character varying`, for precisely
  the consumer shape this package documents as correct. The cast is now unconditional
  in the column-to-column position. Ordinary reads still emit no cast, and the lookup
  index on `(partyable_type, partyable_id)` stays usable because the cast falls on the
  outer, correlated side of the `exists` subquery.

### Removed
- **BREAKING: the `identifier_generator` contract is retired.** Gone:
  `RobinsonRyan\HeyYou\Contracts\IdentifierGenerator`,
  `RobinsonRyan\HeyYou\Support\AutoIncrementGenerator`, the
  `heyyou.identifier_generator` config key, and the service provider binding.

  Nothing behavioural changes with them. `d1e1829` replaced the contract-driven
  column definitions with hardcoded `uuidv7()` DDL and left the apparatus standing:
  `columnDefinition()` had no caller anywhere in the package, and the sole caller of
  `generate()` — `PartyFactory` — builds `Uuid7Generator` by hand rather than
  resolving the contract. Setting the config key to a different generator therefore
  did nothing at all, which is worse than a missing knob: it turned, and the machine
  ignored it. Every migration emits byte-for-byte the same DDL after this change as
  before, because the migrations never consulted the contract.

  This is a **deviation from spec §2.2 and §11.1** ("Identifier columns use the
  configured generator"), recorded deliberately. Genuine pluggability is assumed away
  in four places — the 12 `foreignUuid()` columns the contract has no method for,
  `ConfiguresIdentifiers` hardcoding `$keyType = 'string'`, `partyable_id` being
  varchar, and `PartyMorphOne`'s cast keying off `getKeyType()`. Restoring it would
  mean re-opening database portability, and PostgreSQL 18+ is a hard requirement
  because `uuidv7()` cannot be expressed anywhere else.

  **Migration path:** delete `identifier_generator` from any published
  `config/heyyou.php`, and drop any type-hint on the removed contract.
  `Uuid7Generator` remains as a plain final class for the one job it does — handing
  out an identifier before the row exists (`Model::factory()->make()`, or a
  client-supplied ID) — and now carries the test coverage it never had, including
  that v7 values sort in creation order.
- **BREAKING: Laravel 11 support is dropped.** The three `illuminate/*` requirements
  narrow `^11.0|^12.0|^13.0` → `^12.0|^13.0`, and `orchestra/testbench` narrows
  `^9.0|^10.0|^11.0` → `^10.0|^11.0` (testbench 9 *is* Laravel 11).

  It was advertised and never once exercised. This package came closer than its
  siblings — it allows `pestphp/pest ^3|^4|^5` and `phpunit/phpunit ^11|^12|^13`, so
  Laravel 11 was reachable on paper via Pest 3 — but no local harness ever resolved
  it. `--prefer-lowest` takes PHPUnit at 11.0.0, testbench 9 requires
  `phpunit/phpunit ^11.0.1`, so testbench floats up to 10 and the floor run lands on
  Laravel 12; every other run resolves the ceiling. Nothing in CI or in
  `harness package-check` has ever put this package on Laravel 11, so the constraint
  was a promise backed by no evidence. Declaring only what is tested is the point.

  **Breaking for anyone pinned to Laravel 11** — such a consumer can no longer
  install this package and must upgrade to Laravel 12 or 13. Everyone else sees no
  change: the resolved dependency set is byte-for-byte identical (Laravel 13.24,
  testbench 11.1, Pest 5.0.4, PHPUnit 13.2.6).

  `pestphp/pest`, `pestphp/pest-plugin-laravel` and `phpunit/phpunit` keep their
  full `^3|^4|^5` / `^11|^12|^13` ranges deliberately. Pest 3 and PHPUnit 11 both
  pair with Laravel 12, so narrowing them would drop a rung that genuinely works.

## [0.1.2] - 2026-08-08

### Added
- **Consumer models on integer primary keys are supported.** `Contactable::party()`
  now returns a `PartyMorphOne` (`src/Relations/PartyMorphOne.php`) instead of a plain
  `morphOne`. `heyyou_parties.partyable_id` is a `varchar` because the package's own
  models carry UUID7 keys; a consumer on an auto-incrementing `bigint` key bound its
  key as an integer, and PostgreSQL refuses `character varying = bigint` outright
  (`SQLSTATE[42883]`) rather than coercing it the way SQLite silently did. The new
  relation coerces bound keys to text, forces the ordinary bound `whereIn` in place of
  Eloquent's `whereIntegerInRaw` eager-load optimisation (which inlines bare integers
  past the binding layer), and wraps the correlated column of an existence query in a
  `cast(... as varchar)`. UUID-keyed consumers already have a string key, so their
  queries are byte-for-byte unchanged.

### Changed
- **Constraint matrix widened.** `php` `^8.3` → `^8.2` (restores PHP 8.2 support that
  v0.1.1 dropped; Laravel 11/12 still support it), dev matrix now allows
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
