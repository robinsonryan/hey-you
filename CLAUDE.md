# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HeyYou is a Laravel package for modeling contactable entities, contact methods, and deterministic contact resolution.

**Namespace:** `RobinsonRyan\HeyYou`
**PHP:** 8.2+
**Laravel:** 11.x, 12.x

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
// CORRECT: Tell Laravel the PK is a non-incrementing string, but do NOT generate it
class Party extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    // That's it. No HasUuids, no newUniqueId(), no boot/creating hooks for IDs.
}
```

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
| Model PK config | `$incrementing = false; $keyType = 'string';` | `use HasUuids;` |
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
ddev start           # Start environment
ddev test            # Run tests
ddev quality         # Full quality checks
```

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
$user->party; // Party instance
$user->contactPoints; // HasManyThrough to contact points
```

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

Uses Pest with Orchestra Testbench. Tests run against SQLite in-memory.

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
