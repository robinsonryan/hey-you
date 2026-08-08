# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HeyYou is a Laravel package for modeling contactable entities, contact methods, and deterministic contact resolution.

**Namespace:** `RobinsonRyan\HeyYou`
**PHP:** 8.2 – 8.5
**Laravel:** 11.x, 12.x, 13.x
**Dev matrix:** testbench 9/10/11, Pest 3/4/5, PHPUnit 11/12/13 (Pest 5 + PHPUnit 13
needs PHP 8.4; on PHP 8.2/8.3 Composer resolves the older rungs)
**Database:** PostgreSQL 18+ only — the schema uses native `uuidv7()` column defaults

## UUID7 Primary Key Conventions (CRITICAL)

This project uses PostgreSQL 18 with its native `uuidv7()` function. The DATABASE generates UUIDs, NOT Laravel.

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

The `Uuid7Generator` class (`src/Support/Uuid7Generator.php`) exists ONLY for cases where you need an ID before the record hits the database (e.g., `Model::factory()->make()` without persisting, or client-side ID generation for specific business reasons). In normal CRUD operations, Postgres generates the ID via `uuidv7()`.

### Quick Reference

| Concern | Correct Approach | Wrong Approach |
|---------|-----------------|----------------|
| PK in migration | `$table->uuid('id')->primary()->default(DB::raw('uuidv7()'))` | `$table->id()` |
| FK in migration | `$table->uuid('col')` or `$table->foreignUuid('col')` | `$table->foreignId('col')` |
| Model PK config | `use ConfiguresIdentifiers;` (`$incrementing = true; $keyType = 'string';`) | `use HasUuids;` or `$incrementing = false` |
| UUID generation | Postgres `uuidv7()` at insert time | `Str::uuid7()` in model boot |
| Pre-persist IDs | `Uuid7Generator::generate()` (rare, only when needed) | `HasUuids` trait |

## Development Commands

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run single test
./vendor/bin/pest --filter="test name"

# Run tests with coverage
composer test:coverage

# Static analysis (PHPStan level 8)
composer analyze

# Code formatting (Laravel Pint)
composer lint

# Full quality check (lint, analyze, test)
composer quality
```

### DDEV Commands

```bash
ddev start           # Start environment (also creates the `testing` database)
ddev test            # Run tests
ddev quality         # Full quality checks (Pint --test, PHPStan level 8, Rector --dry-run, Pest)
```

`ddev start`'s post-start hook creates the `testing` Postgres database the suite
needs. `composer quality` runs `lint:check → analyze → refactor:check → test`, so
Rector drift is gated rather than accumulating unseen
[T: composer.json `scripts.quality`].

## Architecture

### Directory Structure

```
src/
├── Contracts/               # Interfaces
│   └── Registries/          # Registry contracts
├── Models/                  # Eloquent models (10 models)
├── Traits/                  # Contactable trait
├── Events/                  # Domain events
│   ├── Party/               # PartyCreated, PartyUpdated, PartyDeleted
│   ├── ContactPoint/        # ContactPointCreated, Updated, Verified, Deleted
│   ├── Consent/             # ConsentGranted, ConsentRevoked
│   ├── Dnc/                 # DncRuleCreated, DncRuleRemoved
│   └── Resolver/            # ContactResolved
├── Registries/              # Config-based registry implementations
├── Normalizers/             # Email, Phone normalizers
├── Resolver/                # Contact resolution engine
├── Checkers/                # DNC, Consent, Scope hierarchy checkers
└── Support/                 # Utilities (generators, result objects)

database/
├── migrations/              # 10 database migrations
└── factories/               # Model factories
```

### Core Models

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
| `VerificationEvent` | `heyyou_verification_events` | Verification history (optional) |

### Core Contracts

| Contract | Default Implementation | Description |
|----------|----------------------|-------------|
| `ContactResolver` | `DefaultContactResolver` | Contact resolution algorithm |
| `DncChecker` | `DefaultDncChecker` | DNC rule checking |
| `ConsentChecker` | `DefaultConsentChecker` | Consent verification |
| `ScopeHierarchyResolver` | `RelationshipBasedScopeResolver` | Scope traversal |
| `ChannelNormalizer` | `EmailNormalizer`, `PhoneNormalizer` | Value normalization |
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

### Core Concepts

#### 1. Contactable Trait
Consumer models use the `Contactable` trait to integrate with HeyYou:

```php
class User extends Model
{
    use Contactable;

    public function getDisplayNameForParty(): string
    {
        return $this->name;
    }
}

// Party is auto-created when consumer is created
$user = User::create(['name' => 'John']);
$user->party;          // Party instance
$user->contactPoints;  // HasManyThrough, hopping the party
$user->addresses;      // HasManyThrough, hopping the party
```

The trait supplies three relations. All of them go through custom relation
classes in `src/Relations/`, never plain `morphOne`/`hasManyThrough`:

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

#### 2. Contact Points with Normalization
```php
$party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => 'John.Doe@Example.COM',  // Auto-normalized to john.doe@example.com
    'is_primary' => true,
]);
```

Channels: email, phone, sms, whatsapp, signal, facebook, instagram, linkedin, twitter, tiktok

#### 3. Contact Resolution
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

**Resolution Algorithm** (fixed priority):
1. Exclusions (DNC, consent, blocked status)
2. Status ranking (active > inactive > bounced > unreachable)
3. Verification status
4. Purpose match (exact > parent > none)
5. Scope specificity
6. Primary flag
7. Priority field
8. Created date (tiebreaker)

#### 4. Policy Layer
- **PartyConsent/ContactPointConsent**: Layered consent (contact-point overrides party)
- **DoNotContact**: Blocking rules by party, channel, purpose, or contact point
- **Precedence**: Contact-point-specific > channel-specific > generic

## Testing

Uses Pest with Orchestra Testbench, running against **a real PostgreSQL 18 database**
(the DDEV `db` service, database `testing`) — not SQLite. The schema depends on
`uuidv7()` column defaults, which SQLite cannot express, so an in-memory SQLite run
fails on the very first migration.

`tests/TestCase.php` uses `RefreshDatabase`: migrations run once per process and each
test is wrapped in a transaction. The connection is overridable via
`HEYYOU_TEST_DB_HOST` / `_PORT` / `_DATABASE` / `_USERNAME` / `_PASSWORD`.

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
│   ├── Models/
│   ├── Checkers/
│   ├── Normalizers/
│   └── ...
└── Fixtures/
    └── Models/              # Test models (User, Company, Location)
```

### Factories

```php
Party::factory()->person()->create();
Party::factory()->organization()->create();
ContactPoint::factory()->email()->verified()->primary()->create();
Address::factory()->billing()->forParty($party)->create();
RoleAssignment::factory()->accountsPayable()->forParty($person)->scopedTo($company)->create();
```

## Key Files

- `config/heyyou.php` - Configuration (table prefix, registries, channels, purposes, roles)
- `src/HeyYouServiceProvider.php` - Service container bindings
- `src/Resolver/DefaultContactResolver.php` - Contact resolution algorithm
- `src/Traits/Contactable.php` - Trait for consumer models
- `src/Checkers/DefaultDncChecker.php` - DNC policy checking
- `src/Checkers/DefaultConsentChecker.php` - Consent policy checking
- `src/Checkers/RelationshipBasedScopeResolver.php` - Scope hierarchy traversal

## Events Dispatched

- **Party**: `PartyCreated`, `PartyUpdated`, `PartyDeleted`, `PartyRestored`
- **ContactPoint**: `ContactPointCreated`, `ContactPointUpdated`, `ContactPointVerified`, `ContactPointDeleted`, `ContactPointRestored`, `ContactPointBounced`, `ContactPointMarkedUnreachable`, `ContactPointVerificationFailed`, `ContactPointVerificationExpired`, `ContactPointPurposeAttached`, `ContactPointPurposeDetached`
- **Address**: `AddressCreated`, `AddressUpdated`, `AddressDeleted`, `AddressRestored`, `AddressValidated`, `AddressValidationFailed`
- **Relationship**: `RelationshipCreated`, `RelationshipUpdated`, `RelationshipEnded`, `RelationshipDeleted`
- **RoleAssignment**: `RoleAssignmentCreated`, `RoleAssignmentUpdated`, `RoleAssignmentExpired`, `RoleAssignmentDeleted`
- **Consent**: `ConsentGranted`, `ConsentRevoked`
- **DNC**: `DncRuleCreated`, `DncRuleRemoved`
- **Resolver**: `ContactResolved`

## Documentation

Full documentation available in `docs/`:

- [Quickstart Guide](docs/quickstart.md)
- [Installation](docs/installation.md)
- [Contact Points](docs/contact-points.md)
- [Contact Resolution](docs/resolver.md)
- [Policies (Consent & DNC)](docs/policies.md)
- [Party Relationships & Roles](docs/relationships.md)
- [Addresses](docs/addresses.md)
- [Events](docs/events.md)
- [Custom Registries](docs/registries.md)
- [Configuration](docs/configuration.md)
- [Full Specification](docs/spec.md)
