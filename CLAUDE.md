# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HeyYou is a Laravel package for modeling contactable entities, contact methods, and deterministic contact resolution.

**Namespace:** `RobinsonRyan\HeyYou`
**PHP:** 8.2+
**Laravel:** 11.x, 12.x

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
├── Models/                  # Eloquent models
├── Traits/                  # Contactable trait
├── Events/                  # Domain events
│   ├── Party/
│   ├── ContactPoint/
│   ├── Consent/
│   ├── Dnc/
│   └── Resolver/
├── Registries/              # Registry implementations
├── Normalizers/             # Channel normalizers
├── Resolver/                # Contact resolution
├── Checkers/                # Policy checkers
└── Support/                 # Utilities

database/
├── migrations/              # Database migrations
└── factories/               # Model factories
```

### Core Concepts

#### 1. Party Model (Identity Map)
The `Party` model is a thin identity map linking to consumer models (User, Company, Location).

```php
// Consumer models use the Contactable trait
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
```

#### 2. Contact Points
Contact points store normalized contact information with channel-specific normalization.

```php
$party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => 'John.Doe@Example.COM',  // Auto-normalized to john.doe@example.com
    'is_primary' => true,
]);
```

Channels: email, phone, sms, whatsapp, signal, facebook, instagram, linkedin, twitter, tiktok

#### 3. Party Relationships
Links parties together with typed relationships.

```php
PartyRelationship::create([
    'from_party_id' => $employee->party->id,
    'to_party_id' => $company->party->id,
    'relationship_type' => 'employment',  // employment, contractor, location_of, member_of, parent_of
]);
```

#### 4. Role Assignments
Assigns roles to parties within a scope (organization/location).

```php
RoleAssignment::create([
    'party_id' => $person->party->id,
    'scope_party_id' => $company->party->id,
    'role' => 'accounts_payable_contact',
    'priority' => 1,
]);
```

#### 5. Policy Layer (Consent & DNC)
- **PartyConsent**: Party-level consent by channel/category
- **ContactPointConsent**: Contact-point-level consent (overrides party)
- **DoNotContact**: Blocking rules by party, channel, purpose, or contact point

#### 6. Contact Resolution
The resolver finds the best contact points based on purpose, channel, and policies.

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

### Registry System

All classification values come from registries (not enums):

- `ChannelRegistry` - email, phone, sms, etc.
- `PurposeRegistry` - billing, shipping, hr, etc.
- `RoleRegistry` - accounts_payable_contact, hr_contact, etc.
- `RelationshipTypeRegistry` - employment, location_of, etc.
- `ConsentCategoryRegistry` - transactional, marketing, etc.
- `NormalizerRegistry` - channel-specific normalizers

Default implementations use config arrays. Replace with custom implementations for database-backed registries.

### Events

Events are dispatched for all model lifecycle changes:
- Party: Created, Updated, Deleted
- ContactPoint: Created, Updated, Verified, Deleted
- Consent: Granted, Revoked
- DNC: RuleCreated, RuleRemoved
- Resolver: ContactResolved

### Factories

Model factories are provided for testing:

```php
Party::factory()->person()->create();
Party::factory()->organization()->create();
ContactPoint::factory()->email()->verified()->primary()->create();
Address::factory()->billing()->forParty($party)->create();
RoleAssignment::factory()->accountsPayable()->forParty($person)->scopedTo($company)->create();
```

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
    └── Models/              # Test models (User, Company)
```

## Key Files

- `config/heyyou.php` - Configuration (table prefix, registries, channels, purposes, roles)
- `src/HeyYouServiceProvider.php` - Service container bindings
- `src/Resolver/DefaultContactResolver.php` - Contact resolution algorithm
- `src/Traits/Contactable.php` - Trait for consumer models
