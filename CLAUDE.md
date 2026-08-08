# HeyYou

Models parties (people and organizations), the relationships between them, their
contact points and addresses, and resolves "who do I actually contact for X" —
deterministically, with consent and do-not-contact rules applied. CRM-shaped
domain modelling, no messaging transport.

Composer name: `robinsonryan/hey-you` — a **library**, not an application.

## Conventions

@import ./constitution.md
@import ./imports/package-conventions.md
@import ./imports/package-quality-gate.md
@import ./imports/testing-conventions.md
@import ./imports/php-conventions.md
@import ./imports/git-conventions.md

> Linking the `laravel-package` stack also drops the inherited Laravel app
> conventions into `.claude/imports/` — `authorization-conventions.md`,
> `frontend-conventions.md`, `pwa-conventions.md`, `ddev-worktrees.md`. They are
> **deliberately not imported above**: they describe Inertia `can` maps, app-shaped
> Vite wiring, and nested app worktrees, none of which exist in a package. Read
> them if a question genuinely calls for one; do not load them by default.
>
> `frontend-conventions.md` stays out for good here — this package has no frontend
> half, no `package.json`, and no JS side to the gate.

> `.claude/` is a set of **harness symlinks** and is gitignored — a fresh clone has
> none of them and the `@import`s above resolve to nothing. If a convention file is
> missing, restore the link rather than guessing:
> `~/workspace/harness/link.sh project laravel-package $(pwd)`

## Version matrix

**Namespace:** `RobinsonRyan\HeyYou`
**PHP:** 8.2 – 8.5
**Laravel:** 12.x, 13.x
**Dev matrix:** testbench 10/11, Pest 3/4/5, PHPUnit 11/12/13 (Pest 5 + PHPUnit 13
needs PHP 8.4; on PHP 8.2/8.3 Composer resolves the older rungs)
**Database:** PostgreSQL 18+ only — the schema uses native `uuidv7()` column defaults

Laravel 11 was dropped in `[Unreleased]`: it was advertised for months and never once
resolved locally. `--prefer-lowest` takes PHPUnit 11.0.0, testbench 9 requires
`phpunit/phpunit ^11.0.1`, so testbench floated to 10 and the floor run landed on
Laravel 12 anyway. Don't re-add `^11.0` without a harness run that actually exercises
it. Pest and PHPUnit keep their full ranges on purpose — Pest 3 and PHPUnit 11 both
pair with Laravel 12, so narrowing them would drop a rung that works
[T: composer.json].

## This package is the reference implementation

The harness conventions name hey-you as the package other packages are copied from —
service provider shape, Testbench setup, table prefixing, and the canonical
`pint.json` / `phpstan.neon` / `rector.php`
[T: .claude/imports/package-conventions.md, .claude/imports/package-quality-gate.md].

That raises the cost of a casual change here. Editing a tool config or `TestCase.php`
in this repo is not a local decision — it is a decision for seven packages, and the
next one scaffolded will inherit whatever it finds. Change them deliberately, and say
in the commit message that the reference moved.

## The gate

`ddev composer quality` — `lint:check` → `analyze` → `refactor:check` → `test`.
Verify-only: it never rewrites files. Fix with `ddev composer lint` /
`ddev composer refactor` and re-stage.

`.githooks/pre-commit` runs **the whole gate, tests included** — packages are small
enough that the apps' exclude-the-tests compromise does not apply. It is path-aware,
so a docs-only commit skips it. Never bypass with `--no-verify`; `PACKAGE_SKIP_GATE=1`
is a human emergency valve and **agents must never set it**.

This is the largest of the seven suites — 358 tests, measured 28.6 s for the suite and
36 s for the whole hook on a warm cache (2026-08-08). Budget for it; don't assume the
13–21 s the other packages take.

That hook file is a **copy** of the harness's canonical one. Do not edit it here —
edit `$CLAUDE_HARNESS_DIR/core/stacks/laravel-package/hooks/pre-commit` and re-run
that directory's `install.sh`.

`harness package-check` sweeps every first-party package: the gate, a
`--prefer-lowest` run proving the declared version floor really resolves, outdated and
vulnerability scans, and in-constraint updates behind a re-run of the gate. It never
tags a release. Run it before any app re-resolves its packages.

Full definition: `imports/package-quality-gate.md`. Skill: `/package-quality`.

### What PHPStan actually reads

Level 8, but over **`src` and `tests/Fixtures` only** — not `tests/` proper
[T: phpstan.neon]. The fixture consumer models are in scope because they are the only
classes that use the `Contactable` trait, and without them the trait reports as unused.
Everything else under `tests/` is unanalysed, so a type error in a test file will not
be caught by `analyze` — only by the test failing. `phpVersion: 80200` pins the
analyser to the `php: ^8.2` floor so it cannot silently accept 8.3+ syntax.

## Testing

Pest + Orchestra Testbench against **a real PostgreSQL 18 database** — the DDEV `db`
service, database `testing` — not SQLite [T: tests/TestCase.php]. This is not a
stylistic choice: the schema depends on `uuidv7()` column defaults, which SQLite
cannot express, so an in-memory run fails on the very first migration. (Several
sibling packages do run on in-memory SQLite; do not carry their `TestCase` over here.)

```bash
ddev composer test
ddev composer quality
ddev exec vendor/bin/pest --filter=SomeTest
```

There is no `ddev artisan` and no `ddev pest` here — those are app commands. `ddev test`
and `ddev quality` exist as thin host wrappers over the composer scripts
[T: .ddev/commands/host/test].

`ddev start`'s post-start hook creates the `testing` database the suite needs.

`tests/TestCase.php` uses `RefreshDatabase`: migrations run once per process and each
test is wrapped in a transaction. The connection is overridable via
`HEYYOU_TEST_DB_HOST` / `_PORT` / `_DATABASE` / `_USERNAME` / `_PASSWORD` — that last
one is how a worktree gets its own `testing_wt_*` database. Fixture tables live in
`tests/Fixtures/database/migrations` and are registered with the migrator via
`afterResolving('migrator')` rather than `loadMigrationsFrom()`, deliberately: the
latter makes Testbench tear the schema down and rebuild it per test.

Fixture consumer models (`tests/Fixtures/Models/{User,Company}`) carry UUID7 keys like
real consumers do, so a placeholder `partyable_id` must be a UUID — use the
`fakePartyableId()` helper from `tests/Pest.php`, never an integer.

```
tests/
├── Feature/
│   ├── Integration/         # End-to-end workflow tests
│   ├── ContactResolutionTest.php
│   ├── ResolverRankingTest.php
│   └── ...
├── Unit/
│   ├── Models/  Checkers/  Normalizers/  Registries/  Resolver/  Support/  Events/
│   └── Traits/
└── Fixtures/
    ├── Models/              # Test consumer models (User, Company, Location)
    └── database/migrations/ # Their tables
```

### Factories

```php
Party::factory()->person()->create();
Party::factory()->organization()->create();
ContactPoint::factory()->email()->verified()->primary()->create();
Address::factory()->billing()->forParty($party)->create();
RoleAssignment::factory()->accountsPayable()->forParty($person)->scopedTo($company)->create();
```

## UUID7 Primary Key Conventions (CRITICAL)

This package uses PostgreSQL 18 with its native `uuidv7()` function. The DATABASE
generates UUIDs, NOT Laravel.

### In Migrations: Database generates the UUID

```php
// CORRECT: Let PostgreSQL generate the UUID7
$table->uuid('id')->primary()->default(DB::raw('uuidv7()'));

// CORRECT: Foreign keys use uuid(), not foreignId()
$table->uuid('party_id');
$table->foreign('party_id')->references('id')->on('heyyou_parties');
// Or shorthand:
$table->foreignUuid('party_id')->constrained('heyyou_parties');
```

```php
// WRONG: Never use auto-incrementing IDs
$table->id();

// WRONG: Never use foreignId() - that assumes bigint auto-increment
$table->foreignId('party_id');
```

### In Models: Configure for UUID PKs, but do NOT generate them

```php
// CORRECT: Tell Laravel the DB assigns a string PK, but do NOT generate it here
class Party extends Model
{
    public $incrementing = true;   // "the DB assigns the key on insert" — NOT auto-increment
    protected $keyType = 'string'; // it is a UUID, not an int
    // That's it. No HasUuids, no newUniqueId(), no boot/creating hooks for IDs.
}
```

`$incrementing = true` reads wrong at first glance. In Eloquent it does not mean
"auto-increment integer" — it means "the database assigns the key during INSERT",
which is exactly what a `uuidv7()` column default does. It is what makes Eloquent
compile the INSERT through `insertGetId()`, which on PostgreSQL appends
`returning "id"` and hydrates the generated UUID back onto the model. With
`$incrementing = false` the row still gets its UUID in the database, but the model
returned by `create()` has a **null key** — every downstream relation write then
fails. Use the `ConfiguresIdentifiers` trait rather than setting these by hand.

```php
// WRONG: Never use Laravel's HasUuids trait
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Party extends Model
{
    use HasUuids; // NO! This makes Laravel generate UUIDs, bypassing Postgres uuidv7()
}

// WRONG: Never override newUniqueId()
public function newUniqueId(): string
{
    return Str::uuid7()->toString(); // NO! Postgres handles this.
}
```

### The ONE Exception: Pre-persist ID Generation

`Uuid7Generator` (`src/Support/Uuid7Generator.php`) exists ONLY for cases where you
need an ID before the record hits the database — `Model::factory()->make()` without
persisting, a client-supplied ID, or `fakePartyableId()` in tests. In normal CRUD,
Postgres generates the ID.

It is a plain final class, **not** a contract implementation. The
`IdentifierGenerator` contract, `AutoIncrementGenerator`, the
`heyyou.identifier_generator` config key and its service-provider binding were all
retired in `[Unreleased]` — a knob that turned and did nothing. Migrations emit
hardcoded `uuidv7()` DDL and never consulted the contract, `columnDefinition()` had no
caller anywhere, and the sole caller of `generate()` built the generator by hand. This
is a deliberate deviation from `docs/spec.md` §2.2 and §11.1, recorded in
`CHANGELOG.md` and `docs/plans/2026-08-08-finish-half-wired-features.md` (D2). Do not
"restore" it: genuine pluggability would mean re-opening database portability, and
PostgreSQL 18+ is a hard requirement because `uuidv7()` cannot be expressed elsewhere.

### Quick Reference

| Concern | Correct Approach | Wrong Approach |
|---------|-----------------|----------------|
| PK in migration | `$table->uuid('id')->primary()->default(DB::raw('uuidv7()'))` | `$table->id()` |
| FK in migration | `$table->uuid('col')` or `$table->foreignUuid('col')` | `$table->foreignId('col')` |
| Model PK config | `use ConfiguresIdentifiers;` (`$incrementing = true; $keyType = 'string';`) | `use HasUuids;` or `$incrementing = false` |
| UUID generation | Postgres `uuidv7()` at insert time | `Str::uuid7()` in model boot |
| Pre-persist IDs | `Uuid7Generator::generate()` (rare, only when needed) | `HasUuids` trait |

## Architecture

### Directory Structure

```
src/
├── Contracts/               # Interfaces
│   └── Registries/          # Registry contracts
├── Models/                  # Eloquent models (10)
├── Traits/                  # Contactable, ConfiguresIdentifiers
├── Relations/               # PartyMorphOne, PartyHasManyThrough (+ Concerns/)
├── Events/                  # Domain events (Party, ContactPoint, Address,
│                            #   Relationship, RoleAssignment, Consent, Dnc, Resolver)
├── Registries/              # Config-based registry implementations
├── Normalizers/             # Email, Phone normalizers
├── Resolver/                # Contact resolution engine
├── Checkers/                # DNC, Consent, Scope hierarchy checkers
└── Support/                 # Uuid7Generator, TablePrefixer, result objects

database/
├── migrations/              # 10 migrations, one per table
└── factories/               # Model factories
```

### Tables this package owns

Every table is prefixed from `heyyou.table_prefix` (default `heyyou_`) via
`Support/TablePrefixer`. The package owns these ten and nothing else — never reference
a consumer's `users` or `teams` table from package code.

| Model | Table | Description |
|-------|-------|-------------|
| `Party` | `heyyou_parties` | Identity map linking to consumer models |
| `PartyRelationship` | `heyyou_party_relationships` | Links between parties (employment, etc.) |
| `ContactPoint` | `heyyou_contact_points` | Email, phone, social handles |
| `ContactPointPurpose` | `heyyou_contact_point_purposes` | Purpose tags on contact points |
| `Address` | `heyyou_addresses` | Physical addresses |
| `RoleAssignment` | `heyyou_role_assignments` | Role assignments within scopes |
| `PartyConsent` | `heyyou_party_consents` | Party-level consent records |
| `ContactPointConsent` | `heyyou_contact_point_consents` | Contact-point-level consent |
| `DoNotContact` | `heyyou_do_not_contacts` | DNC blocking rules |
| `VerificationEvent` | `heyyou_verification_events` | Verification history |

### Service provider

`HeyYouServiceProvider` merges `config/heyyou.php` under the `heyyou` key, binds every
registry and core service from config, publishes under the `heyyou-config` and
`heyyou-migrations` tags, and loads migrations **only if the consumer has not published
them** — guarded by a `glob()` on `database/migrations/*_create_heyyou_*.php`, so a
consumer who publishes does not get them registered twice.

### Core Contracts

| Contract | Default Implementation | Description |
|----------|----------------------|-------------|
| `ContactResolver` | `DefaultContactResolver` | Contact resolution algorithm |
| `DncChecker` | `DefaultDncChecker` | DNC rule checking |
| `ConsentChecker` | `DefaultConsentChecker` | Consent verification |
| `ScopeHierarchyResolver` | `RelationshipBasedScopeResolver` | Scope traversal |
| `ChannelNormalizer` | `EmailNormalizer`, `PhoneNormalizer` | Value normalization |
| `ContactPointPurposeManager` | `DefaultContactPointPurposeManager` | Purpose attach/detach |
| `EventDispatcher` | `LaravelEventDispatcher` | Event dispatching |

### Registry Contracts

| Contract | Default Implementation |
|----------|----------------------|
| `ChannelRegistry` | `ConfigChannelRegistry` |
| `PurposeRegistry` | `ConfigPurposeRegistry` |
| `RoleRegistry` | `ConfigRoleRegistry` |
| `RelationshipTypeRegistry` | `ConfigRelationshipTypeRegistry` |
| `ConsentCategoryRegistry` | `ConfigConsentCategoryRegistry` |
| `NormalizerRegistry` | `DefaultNormalizerRegistry` |

## Core Concepts

### 1. Contactable trait — the consumer's whole entry point

```php
class User extends Model
{
    use Contactable;

    public function getDisplayNameForParty(): string
    {
        return $this->name;
    }
}

// Party is auto-created when the consumer is created
$user = User::create(['name' => 'John']);
$user->party;          // MorphOne
$user->contactPoints;  // HasManyThrough, hopping the party
$user->addresses;      // HasManyThrough, hopping the party
```

`bootContactable()` also keeps `display_name_cached` in sync on update (only when it
actually changed) and soft-deletes the party when the consumer is deleted.

The trait supplies three relations. All of them go through custom relation classes in
`src/Relations/`, never plain `morphOne`/`hasManyThrough`:

| Method | Class |
|---|---|
| `party()` | `PartyMorphOne` |
| `contactPoints()`, `addresses()` | `PartyHasManyThrough` |

Two reasons they are not the stock relations, and both are load-bearing:

1. **`partyable_id` is a `varchar`**, because the package's own keys are UUID7.
   PostgreSQL will not compare it to a consumer's `bigint` key — nor to a native
   `uuid` key, which is the trap: `getKeyType()` reports `'string'` for a `uuid`
   column exactly as for a `varchar` one, so "the key is a string in PHP" is not
   the same question as "PostgreSQL will compare these two column types." Both
   sides are coerced via `Relations\Concerns\CoercesConsumerKeyToText` — bound
   values stringified, correlated columns wrapped in `cast(... as varchar)`
   [T: tests/Unit/Traits/ContactableIntegerKeyTest.php].
2. **`PartyHasManyThrough` constrains `partyable_type` in `addConstraints()`**,
   not at the call site. Two integer-keyed consumer types both start at id 1, so
   without it a `User` reads a `Company`'s contact points. Putting it in the
   relation means no future caller can forget it
   [T: tests/Unit/Traits/ContactableRelationsTest.php].

`contactPoints()` and `addresses()` hop the party onto `Party::contactPoints()` /
`Party::addresses()` — they are a second *route*, never a second *query path*. If you
add another consumer-level relation, hop the party the same way.

### 2. Contact points with normalization

```php
$party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => 'John.Doe@Example.COM',  // Auto-normalized to john.doe@example.com
    'is_primary' => true,
]);
```

Channels: email, phone, sms, whatsapp, signal, facebook, instagram, linkedin, twitter,
tiktok — all config-driven via `ConfigChannelRegistry`.

### 3. Verification lifecycle

`ContactPoint` has four intent methods; each moves the model, dispatches its event and
writes a `VerificationEvent` row as one act:

```php
$cp->startVerification($method, $evidence = []);   // returns ?VerificationEvent
$cp->markVerified($method, ?Carbon $expiresAt = null);
$cp->markVerificationFailed($method, $reason, $evidence = []);
$cp->markVerificationExpired();
```

They are explicit methods rather than model hooks for a structural reason: a
verification *failure* is not an attribute change, so no `updated` hook can observe
one. That is exactly why `ContactPointVerificationFailed` and
`ContactPointVerificationExpired` sat in the package for months as the only two event
classes with no dispatch site.

`heyyou.verification.log_history` (default `true`) gates the history rows only —
**events always dispatch**, whether or not logging is on. Signalling is never gated by
the audit switch. `verification.default_expiration_days` (default `null` = no expiry)
applies only when no explicit `$expiresAt` is passed. Assigning `is_verified` directly
still works, still dispatches `ContactPointVerified`, and now also records history.

### 4. Contact resolution

```php
$result = app(ContactResolver::class)->resolve(new ResolverRequest(
    targetParty: $company->party,
    purpose: 'accounts_payable',
    channel: 'email',
    constraints: new ResolverConstraints(requireVerified: true),
    limit: 5,
));

$best = $result->best(); // ResolverMatch with contactPoint, owningParty, rank
```

**Resolution algorithm** (fixed priority — the order is the contract, don't reorder it
without a spec change):

1. Exclusions (DNC, consent, blocked status)
2. Status ranking (active > inactive > bounced > unreachable)
3. Verification status
4. Purpose match (exact > parent > none)
5. Scope specificity
6. Primary flag
7. Priority field
8. Created date (tiebreaker)

### 5. Policy layer

- **PartyConsent / ContactPointConsent**: layered consent — contact-point overrides party
- **DoNotContact**: blocking rules by party, channel, purpose, or contact point
- **Precedence**: contact-point-specific > channel-specific > generic

## Events dispatched

As of the `[Unreleased]` work, **no event class in this package lacks a dispatch site**,
and no config key is read by nothing — both verified mechanically. Keep it that way: a
new event class without a dispatcher is a regression, not a placeholder.

- **Party**: `PartyCreated`, `PartyUpdated`, `PartyDeleted`, `PartyRestored`
- **ContactPoint**: `ContactPointCreated`, `ContactPointUpdated`, `ContactPointVerified`, `ContactPointDeleted`, `ContactPointRestored`, `ContactPointBounced`, `ContactPointMarkedUnreachable`, `ContactPointVerificationFailed`, `ContactPointVerificationExpired`, `ContactPointPurposeAttached`, `ContactPointPurposeDetached`
- **Address**: `AddressCreated`, `AddressUpdated`, `AddressDeleted`, `AddressRestored`, `AddressValidated`, `AddressValidationFailed`
- **Relationship**: `RelationshipCreated`, `RelationshipUpdated`, `RelationshipEnded`, `RelationshipDeleted`
- **RoleAssignment**: `RoleAssignmentCreated`, `RoleAssignmentUpdated`, `RoleAssignmentExpired`, `RoleAssignmentDeleted`
- **Consent**: `ConsentGranted`, `ConsentRevoked`
- **DNC**: `DncRuleCreated`, `DncRuleRemoved`
- **Resolver**: `ContactResolved`

## Key files

- `config/heyyou.php` — table prefix, registry bindings, verification settings, channels, purposes, roles
- `src/HeyYouServiceProvider.php` — container bindings, publishing, migration loading
- `src/Traits/Contactable.php` — the consumer-facing entry point
- `src/Relations/` — `PartyMorphOne`, `PartyHasManyThrough`, `Concerns/CoercesConsumerKeyToText`
- `src/Resolver/DefaultContactResolver.php` — resolution algorithm
- `src/Checkers/DefaultDncChecker.php`, `DefaultConsentChecker.php`, `RelationshipBasedScopeResolver.php`

## Releases

**Never tag.** Automation may update, gate, commit and push a branch, then report
"ready to tag" with a suggested version. Ryan cuts every tag. A version number is a
claim about behavior that a green gate cannot substantiate.

Behavior changes land in `CHANGELOG.md` in the commit that makes them.

## Documentation

- [Quickstart Guide](docs/quickstart.md) · [Installation](docs/installation.md)
- [Contact Points](docs/contact-points.md) · [Addresses](docs/addresses.md)
- [Contact Resolution](docs/resolver.md) · [Policies (Consent & DNC)](docs/policies.md)
- [Party Relationships & Roles](docs/relationships.md) · [Events](docs/events.md)
- [Custom Registries](docs/registries.md) · [Configuration](docs/configuration.md)
- [Full Specification](docs/spec.md) · [Build Plan](docs/BUILD_PLAN.md)
- Plans: `docs/plans/` — most recently `2026-08-08-finish-half-wired-features.md`

## Quick reference

- **DDEV**: `ddev start`, `ddev ssh`
- **Gate**: `ddev composer quality`
- **Tests**: `ddev composer test`
- **One test**: `ddev exec vendor/bin/pest --filter=SomeTest`
- **Style fix**: `ddev composer lint`
- **Rector fix**: `ddev composer refactor`
