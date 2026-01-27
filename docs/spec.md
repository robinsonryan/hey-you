# HeyYou Package Specification

A Laravel package for modeling contactable entities, contact methods, and deterministic contact resolution.

---

## 1. Overview

### 1.1 Purpose

HeyYou provides a complete system for:

- Modeling **people, organizations, and locations** as contactable entities (Parties)
- Managing **multiple contact methods** per entity (email, phone, SMS, WhatsApp, social, etc.)
- Supporting **contextual contact routing** ("contact receiving at Facility 2 via SMS, fallback to email")
- Enforcing **policies** (consent, verification, do-not-contact, priority rules)
- Providing a deterministic **Resolver API** that returns ranked contact options with explanation

### 1.2 Non-Goals

- Not a CRM (no pipelines, opportunities)
- Not a messaging provider (provides "where/how to contact," not sending)
- Not identity/auth (integrates with host app's User system but doesn't require it)
- Not multi-tenant aware (host apps handle tenancy; access flows through tenant-scoped consumer models)

### 1.3 Package Identity

- **Name:** HeyYou
- **Namespace:** `Vendor\HeyYou` (vendor configurable by implementer)
- **Config file:** `config/heyyou.php`
- **Table prefix:** `heyyou_` (configurable, can be set to `null` or `''` to disable)
- **Command prefix:** `heyyou:`

---

## 2. Architectural Decisions

### 2.1 Registry Strategy: Contract-Based

All classification concerns are handled through contracts, not enums or database tables owned by the package.

**Contracts defined:**

- `ChannelRegistry` — email, phone, sms, whatsapp, signal, instagram, etc.
- `PurposeRegistry` — accounts_payable, shipping, hr, billing, etc.
- `RoleRegistry` — ap_contact, receiving_manager, etc.
- `RelationshipTypeRegistry` — employment, contractor, location_of, member_of, etc.
- `ConsentCategoryRegistry` — transactional, marketing, support, collections, etc.

**Contract interface pattern:**

```php
interface ChannelRegistry
{
    public function exists(string $slug): bool;
    public function get(string $slug): RegistryItem;
    public function all(): Collection;
    public function forCategory(string $category): Collection;
}
```

**Implementation note:** The package author will implement these contracts using the Taxon package, where tags can tag tags (e.g., a "channels" tag groups "email," "phone," "sms" tags). Other consumers can implement using enums, config arrays, or their own taxonomy system.

### 2.2 Identifier Strategy: Contract-Based

Party and other model identification is configurable via contract.

**Contract defined:**

```php
interface IdentifierGenerator
{
    public function generate(): string|int;
    public function columnDefinition(Blueprint $table, string $column): void;
}
```

**Implementations:**

- `AutoIncrementGenerator` — standard Laravel `$table->id()` (default)
- Host apps provide custom implementations (e.g., UUID v7 on PostgreSQL 18)

**Migrations:** Publishable so host apps can adjust column types before running.

### 2.3 Party Type Implementation: Thin Identity Map

The package owns a `parties` table that serves as an identity map to consumer models.

**Structure:**

```
parties:
  id                  -- package-generated identifier
  partyable_type      -- morph class of consumer model (User, Company, Location, etc.)
  partyable_id        -- consumer model's primary key
  display_name_cached -- cached display name for performance
  metadata            -- JSON for party-level data relevant to contact resolution
  timestamps
  soft_deletes
```

**Key characteristics:**

- Consumer models (User, Company, etc.) remain the source of truth
- Party record is automatically created/synced via `Contactable` trait
- All contact data hangs off the Party via clean foreign keys
- Consumer models access contact data directly via trait methods (`$user->contactPoints`)

### 2.4 Multi-Tenancy: Host App Responsibility

The package has no tenant awareness. Access flows through consumer models:

1. Host app scopes their models by tenant (however they implement tenancy)
2. Each tenant-scoped consumer model has a corresponding Party record
3. Contact data is accessed through consumer models: `$user->contactPoints`
4. Direct queries on package models (`Party::all()`) are discouraged

---

## 3. Core Layer

### 3.1 Party Model

**Table: `{prefix}parties`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key via IdentifierGenerator |
| partyable_type | string | Morph type of consumer model |
| partyable_id | string/int | Consumer model's primary key |
| display_name_cached | string | Cached display name |
| metadata | json, nullable | Party-level metadata (timezone, language, contact hours) |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft delete |

**Indexes:**

- Unique: `(partyable_type, partyable_id)`
- Index: `(display_name_cached)`

**Relationships:**

- `partyable()` — morphTo consumer model
- `contactPoints()` — hasMany ContactPoint
- `addresses()` — hasMany Address
- `roleAssignments()` — hasMany RoleAssignment (as subject)
- `scopedRoleAssignments()` — hasMany RoleAssignment (as scope)
- `outgoingRelationships()` — hasMany PartyRelationship (as from_party)
- `incomingRelationships()` — hasMany PartyRelationship (as to_party)
- `consents()` — hasMany PartyConsent
- `dncRules()` — hasMany DoNotContact

### 3.2 Contactable Trait

Provided for consumer models to integrate with HeyYou.

```php
trait Contactable
{
    public static function bootContactable(): void
    {
        // Auto-create Party on model creation
        // Auto-update Party display_name_cached on model update
        // Handle model deletion (soft delete Party)
    }

    public function party(): MorphOne
    {
        return $this->morphOne(Party::class, 'partyable');
    }

    public function contactPoints(): HasManyThrough
    {
        // Access contact points directly: $user->contactPoints
    }

    public function addresses(): HasManyThrough
    {
        // Access addresses directly: $user->addresses
    }

    public function getDisplayNameForParty(): string
    {
        // Override in consumer model to customize cached display name
        return $this->name ?? $this->title ?? (string) $this->getKey();
    }
}
```

**Usage in consumer app:**

```php
class User extends Model
{
    use Contactable;

    public function getDisplayNameForParty(): string
    {
        return $this->full_name;
    }
}

class Company extends Model
{
    use Contactable;

    public function getDisplayNameForParty(): string
    {
        return $this->legal_name;
    }
}
```

### 3.3 Party Relationships

**Table: `{prefix}party_relationships`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key |
| from_party_id | fk | Source party |
| to_party_id | fk | Target party |
| relationship_type | string | Slug from RelationshipTypeRegistry |
| label | string, nullable | Human-readable label |
| metadata | json, nullable | Additional data |
| valid_from | timestamp, nullable | Effective start (null = always) |
| valid_to | timestamp, nullable | Effective end (null = ongoing) |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft delete |

**Indexes:**

- Index: `(from_party_id, relationship_type)`
- Index: `(to_party_id, relationship_type)`
- Index: `(relationship_type)`
- Index: `(valid_from, valid_to)`

**Common relationship types (via registry):**

- `employment` — person → organization
- `contractor` — person → organization
- `location_of` — location → organization
- `member_of` — organization → organization
- `parent_of` — organization → organization (subsidiary)
- `managed_by` — location → person/organization

---

## 4. Contact Points

### 4.1 ContactPoint Model

**Table: `{prefix}contact_points`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key |
| party_id | fk | Owning party |
| channel | string | Slug from ChannelRegistry |
| value_raw | string | User-entered value |
| value_normalized | string | Canonical form for deduplication/lookup |
| label | string, nullable | User-provided label ("Work Email", "Dock Phone") |
| status | string | active, inactive, unreachable, bounced, blocked |
| is_primary | boolean | Default for this channel for this party |
| is_verified | boolean | Verification status |
| verified_at | timestamp, nullable | When verified |
| verification_method | string, nullable | code, link, imported, carrier_check, etc. |
| verification_expires_at | timestamp, nullable | Optional expiration |
| metadata | json, nullable | Extension data (extension for phone, etc.) |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft delete |

**Indexes:**

- Unique: `(party_id, channel, value_normalized)` — enforces deduplication
- Index: `(channel, value_normalized)` — for lookups
- Index: `(party_id, channel, status)`
- Index: `(is_verified)`

**Relationships:**

- `party()` — belongsTo Party
- `purposes()` — tagged via Taxon (or through ContactPointPurpose pivot)
- `consents()` — hasMany ContactPointConsent
- `verificationEvents()` — hasMany VerificationEvent (optional history)

### 4.2 Channel Normalization

Each channel has a normalizer implementing the `ChannelNormalizer` contract.

**Contract:**

```php
interface ChannelNormalizer
{
    /**
     * Normalize raw input to canonical form.
     */
    public function normalize(string $raw): string;

    /**
     * Validate that raw input is acceptable for this channel.
     */
    public function validate(string $raw): bool;

    /**
     * Format normalized value for display.
     */
    public function formatForDisplay(string $normalized): string;
}
```

**Normalizer registry contract:**

```php
interface NormalizerRegistry
{
    public function for(string $channel): ChannelNormalizer;
    public function register(string $channel, ChannelNormalizer $normalizer): void;
}
```

**Default normalizers provided:**

| Channel | Normalization Strategy |
|---------|----------------------|
| email | Lowercase, trim, optional punycode for IDN |
| phone | E.164 format |
| sms | E.164 format (same as phone) |

**ContactPoint model hooks:**

```php
protected static function booted(): void
{
    static::saving(function (ContactPoint $contactPoint) {
        $normalizer = app(NormalizerRegistry::class)->for($contactPoint->channel);
        $contactPoint->value_normalized = $normalizer->normalize($contactPoint->value_raw);
    });
}
```

### 4.3 ContactPoint Purposes

Purposes are attached to contact points via Taxon (or a pivot table for non-Taxon implementations).

**Pivot table (for non-Taxon implementations): `{prefix}contact_point_purposes`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key |
| contact_point_id | fk | |
| purpose | string | Slug from PurposeRegistry |
| priority | int | Lower = better (for ordering within purpose) |
| is_preferred | boolean | Preferred for this purpose |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:**

- Unique: `(contact_point_id, purpose)`
- Index: `(purpose, is_preferred)`

**Note:** For Taxon implementations, this pivot is replaced by Taxon's polymorphic tagging. The package defines a contract that abstracts this:

```php
interface ContactPointPurposeManager
{
    public function attach(ContactPoint $contactPoint, string $purpose, array $attributes = []): void;
    public function detach(ContactPoint $contactPoint, string $purpose): void;
    public function purposes(ContactPoint $contactPoint): Collection;
    public function forPurpose(string $purpose): Builder; // Query scope
}
```

### 4.4 Verification Events (Optional History)

**Table: `{prefix}verification_events`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key |
| contact_point_id | fk | |
| status | string | pending, verified, failed, expired |
| method | string | code, link, imported, carrier_check, manual, oauth |
| evidence | json, nullable | Proof/details of verification |
| initiated_at | timestamp | When verification started |
| completed_at | timestamp, nullable | When verification completed |
| expires_at | timestamp, nullable | When verification expires |
| created_at | timestamp | |

**Configuration:**

```php
// config/heyyou.php
'verification' => [
    'log_history' => true, // Enable/disable verification event logging
],
```

---

## 5. Addresses

### 5.1 Address Model

Addresses are owned directly by parties (no shared address pool).

**Table: `{prefix}addresses`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key |
| party_id | fk | Owning party |
| purpose | string | Slug from PurposeRegistry (billing, shipping, warehouse, etc.) |
| is_primary | boolean | Primary address for this purpose |
| label | string, nullable | User-provided label ("Main Warehouse", "HQ") |
| line1 | string | Street address line 1 |
| line2 | string, nullable | Street address line 2 |
| city | string | City/locality |
| region | string, nullable | State/province/region |
| postal_code | string, nullable | ZIP/postal code |
| country_code | string | ISO 3166-1 alpha-2 |
| geocode | json, nullable | {lat, lng} |
| timezone | string, nullable | IANA timezone |
| validation_status | string | unverified, verified, invalid |
| formatted_cached | string, nullable | Pre-formatted display string |
| valid_from | timestamp, nullable | Effective start |
| valid_to | timestamp, nullable | Effective end |
| metadata | json, nullable | Additional data |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft delete |

**Indexes:**

- Index: `(party_id, purpose, is_primary)`
- Index: `(country_code, region, city)`
- Index: `(validation_status)`

---

## 6. Context Layer

### 6.1 Role Assignments

Links a party (usually a person) to a role within a scope (usually an organization or location).

**Table: `{prefix}role_assignments`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key |
| party_id | fk | The party holding the role (usually a person) |
| scope_party_id | fk | The scope context (organization, location) |
| role | string | Slug from RoleRegistry |
| priority | int | For ordering multiple holders of same role |
| valid_from | timestamp, nullable | Effective start (null = always valid) |
| valid_to | timestamp, nullable | Effective end (null = still valid) |
| metadata | json, nullable | Department, language, hours, etc. |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft delete |

**Indexes:**

- Index: `(scope_party_id, role)`
- Index: `(party_id, role)`
- Index: `(role, valid_from, valid_to)`

**Common roles (via registry):**

- `accounts_payable_contact`
- `accounts_receivable_contact`
- `hr_contact`
- `receiving_manager`
- `shipping_contact`
- `sales_contact`
- `support_contact`
- `executive_contact`

**Query patterns:**

```php
// Find current AP contacts for an organization
RoleAssignment::query()
    ->where('scope_party_id', $orgParty->id)
    ->where('role', 'accounts_payable_contact')
    ->current() // scope: valid_from <= now, (valid_to is null OR valid_to >= now)
    ->orderBy('priority')
    ->with('party.contactPoints')
    ->get();
```

### 6.2 Scope Hierarchy

The Resolver traverses scope hierarchy to find contacts. Hierarchy is determined by party relationships.

**Default hierarchy resolution:**

1. Start at specified scope (e.g., Location)
2. Follow `location_of` relationship to Organization
3. Optionally continue to parent organization via `parent_of`

**Scope hierarchy contract:**

```php
interface ScopeHierarchyResolver
{
    /**
     * Get ordered list of scope parties to check, from most specific to most general.
     */
    public function resolve(Party $startingScope): Collection;
}
```

**Default implementation** uses party relationships to build the chain.

---

## 7. Policy Layer

### 7.1 Consent Model (Layered)

Consent exists at both party level and contact-point level. Contact-point-specific overrides party-level.

**Table: `{prefix}party_consents`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key |
| party_id | fk | |
| channel | string | Slug from ChannelRegistry (or null for all channels) |
| purpose_category | string | Slug from ConsentCategoryRegistry |
| status | string | opted_in, opted_out |
| captured_at | timestamp | When consent was captured |
| source | string | web_form, verbal, import, api, etc. |
| evidence | json, nullable | Proof of consent |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft delete |

**Indexes:**

- Index: `(party_id, channel, purpose_category)`
- Index: `(status)`

**Table: `{prefix}contact_point_consents`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key |
| contact_point_id | fk | |
| purpose_category | string | Slug from ConsentCategoryRegistry |
| status | string | opted_in, opted_out |
| captured_at | timestamp | |
| source | string | |
| evidence | json, nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft delete |

**Indexes:**

- Index: `(contact_point_id, purpose_category)`

**Precedence rules:**

1. Contact-point-level consent overrides party-level (more specific wins)
2. Most recent consent wins when timestamps differ
3. Opt-out overrides opt-in at the same specificity level

**Consent checker contract:**

```php
interface ConsentChecker
{
    public function hasConsent(
        ContactPoint $contactPoint,
        string $purposeCategory
    ): ConsentResult;
}

class ConsentResult
{
    public bool $allowed;
    public string $level; // 'contact_point' | 'party' | 'none'
    public ?string $status; // 'opted_in' | 'opted_out' | null
    public ?Carbon $capturedAt;
}
```

### 7.2 Do Not Contact (DNC)

Single table with scope determined by populated fields and tags.

**Table: `{prefix}do_not_contacts`**

| Column | Type | Description |
|--------|------|-------------|
| id | configured | Primary key |
| party_id | fk | Required — the party this DNC applies to |
| contact_point_id | fk, nullable | If set, blocks this specific contact point |
| channel | string, nullable | Slug from ChannelRegistry (if set, channel-specific) |
| purpose | string, nullable | Slug from PurposeRegistry (if set, purpose-specific) |
| reason | string, nullable | Why DNC was applied |
| source | string | compliance, user_request, legal, import, etc. |
| created_by_type | string, nullable | Morph type of actor |
| created_by_id | string/int, nullable | Actor ID |
| effective_at | timestamp | When DNC takes effect |
| expires_at | timestamp, nullable | Optional expiration |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp, nullable | Soft delete |

**Indexes:**

- Index: `(party_id, contact_point_id)`
- Index: `(party_id, channel)`
- Index: `(party_id, purpose)`
- Index: `(effective_at, expires_at)`

**Scope interpretation:**

| party_id | contact_point_id | channel | purpose | Meaning |
|----------|------------------|---------|---------|---------|
| ✓ | null | null | null | Party-wide DNC (no contact at all) |
| ✓ | null | ✓ | null | Don't use this channel for this party |
| ✓ | null | null | ✓ | Don't contact for this purpose |
| ✓ | null | ✓ | ✓ | Don't use this channel for this purpose |
| ✓ | ✓ | null | null | Block this specific contact point |

**DNC checker contract:**

```php
interface DncChecker
{
    public function isBlocked(
        ContactPoint $contactPoint,
        ?string $purpose = null
    ): DncResult;
}

class DncResult
{
    public bool $blocked;
    public ?string $scope; // 'party' | 'channel' | 'purpose' | 'contact_point'
    public ?string $reason;
    public ?DoNotContact $rule;
}
```

### 7.3 Verification Policy

Verification state is checked during resolution.

**ContactPoint verification fields:**

- `is_verified` — boolean
- `verified_at` — when verified
- `verification_method` — how verified
- `verification_expires_at` — optional expiration

**Verification status helper:**

```php
class ContactPoint extends Model
{
    public function isCurrentlyVerified(): bool
    {
        if (!$this->is_verified) {
            return false;
        }

        if ($this->verification_expires_at && $this->verification_expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
```

---

## 8. Resolver Layer

### 8.1 Resolver Interface

```php
interface ContactResolver
{
    public function resolve(ResolverRequest $request): ResolverResult;
}
```

### 8.2 Resolver Request

```php
class ResolverRequest
{
    public function __construct(
        public Party $targetParty,
        public string $purpose,
        public string $channel,
        public ?Party $scopeParty = null, // Defaults to targetParty
        public ?ResolverConstraints $constraints = null,
        public int $limit = 10,
    ) {}
}

class ResolverConstraints
{
    public bool $requireVerified = false;
    public bool $requireConsent = false;
    public ?string $consentCategory = null; // Required if requireConsent = true
    public bool $allowFallback = true;
    public array $excludeContactPointIds = [];
}
```

### 8.3 Resolver Result

```php
class ResolverResult
{
    /** @var Collection<ResolverMatch> */
    public Collection $matches;
    
    public ResolverExplanation $explanation;
    
    public function best(): ?ResolverMatch
    {
        return $this->matches->first();
    }
    
    public function isEmpty(): bool
    {
        return $this->matches->isEmpty();
    }
}

class ResolverMatch
{
    public ContactPoint $contactPoint;
    public Party $owningParty;
    public string $channel;
    public string $normalizedValue;
    public ?string $matchedPurpose; // Exact, parent, or null
    public ?string $matchedRole; // If found via role assignment
    public ?Party $scopeParty; // Which scope this came from
    public array $flags; // verified, consent_ok, is_primary, etc.
    public int $rank;
}

class ResolverExplanation
{
    public int $candidatesConsidered;
    public array $exclusionSummary; // ['dnc' => 2, 'no_consent' => 1, 'bounced' => 3]
    public bool $fallbackUsed;
    public ?string $fallbackPath; // 'location:45 → org:12'
}
```

### 8.4 Resolution Algorithm

**Fixed priority order (rule-based ranking):**

1. **Exclusions** — Remove from consideration:
   - DNC violation
   - Consent missing (when required)
   - Status is `blocked` or `invalid`

2. **Status ranking:**
   - `active` > `inactive` > `bounced` > `unreachable`

3. **Verification:**
   - Verified (and not expired) > Unverified

4. **Purpose match:**
   - Exact purpose match > Parent purpose match > No purpose tag

5. **Scope specificity:**
   - Direct scope match > Parent scope (location → org → global)

6. **Primary flag:**
   - `is_primary = true` > `is_primary = false`

7. **Priority field:**
   - Lower value wins

8. **Created date (final tiebreaker):**
   - Older (established) > Newer (deterministic ordering)

**Resolution order when contacting an Organization/Location:**

1. **Scope Role Holders**
   - Find people with matching role in the scope
   - Collect their contact points matching channel + purpose

2. **Scope Shared Contact Points**
   - Contact points owned directly by the scope party (e.g., AP inbox owned by org)

3. **Scope Generic**
   - Primary contact point for that channel for the scope party

4. **Fallback Up Scope Hierarchy** (if `allowFallback = true`)
   - Location → Organization → Global

5. **Emergency/Last Resort** (if configured)
   - Generic main line / front desk

At every stage: apply policy filters and ranking rules.

### 8.5 Resolver Implementation

```php
class DefaultContactResolver implements ContactResolver
{
    public function __construct(
        private ScopeHierarchyResolver $scopeResolver,
        private DncChecker $dncChecker,
        private ConsentChecker $consentChecker,
        private PurposeRegistry $purposeRegistry,
        private RoleRegistry $roleRegistry,
    ) {}

    public function resolve(ResolverRequest $request): ResolverResult
    {
        $candidates = collect();
        $exclusions = ['dnc' => 0, 'no_consent' => 0, 'status' => 0];
        $fallbackUsed = false;
        $fallbackPath = [];

        $scopeChain = $this->scopeResolver->resolve(
            $request->scopeParty ?? $request->targetParty
        );

        foreach ($scopeChain as $scope) {
            $scopeCandidates = $this->gatherCandidates($scope, $request);
            
            foreach ($scopeCandidates as $candidate) {
                $filterResult = $this->applyFilters($candidate, $request);
                
                if ($filterResult->excluded) {
                    $exclusions[$filterResult->reason]++;
                    continue;
                }
                
                $candidates->push($candidate);
            }

            if ($candidates->isNotEmpty() && !$request->constraints?->allowFallback) {
                break;
            }

            if ($candidates->isEmpty()) {
                $fallbackUsed = true;
                $fallbackPath[] = $scope->id;
            }
        }

        $ranked = $this->rank($candidates);

        return new ResolverResult(
            matches: $ranked->take($request->limit),
            explanation: new ResolverExplanation(
                candidatesConsidered: $candidates->count() + array_sum($exclusions),
                exclusionSummary: $exclusions,
                fallbackUsed: $fallbackUsed,
                fallbackPath: $fallbackPath ? implode(' → ', $fallbackPath) : null,
            ),
        );
    }
}
```

---

## 9. Events

### 9.1 Event Dispatcher Contract

```php
interface EventDispatcher
{
    public function dispatch(object $event): void;
}
```

**Default Laravel implementation:**

```php
class LaravelEventDispatcher implements EventDispatcher
{
    public function dispatch(object $event): void
    {
        event($event);
    }
}
```

### 9.2 Event Classes

**Party Events:**

- `PartyCreated` — party, partyable
- `PartyUpdated` — party, partyable, changedAttributes
- `PartyDeleted` — party, partyable
- `PartyRestored` — party, partyable

**ContactPoint Events:**

- `ContactPointCreated` — contactPoint, party
- `ContactPointUpdated` — contactPoint, party, changedAttributes
- `ContactPointDeleted` — contactPoint, party
- `ContactPointRestored` — contactPoint, party
- `ContactPointVerified` — contactPoint, method, verifiedAt
- `ContactPointVerificationFailed` — contactPoint, method, reason
- `ContactPointVerificationExpired` — contactPoint
- `ContactPointBounced` — contactPoint, bounceInfo
- `ContactPointMarkedUnreachable` — contactPoint, reason
- `ContactPointPurposeAttached` — contactPoint, purpose, attributes
- `ContactPointPurposeDetached` — contactPoint, purpose

**Address Events:**

- `AddressCreated` — address, party
- `AddressUpdated` — address, party, changedAttributes
- `AddressDeleted` — address, party
- `AddressRestored` — address, party
- `AddressValidated` — address, validationResult
- `AddressValidationFailed` — address, validationResult

**Consent Events:**

- `ConsentGranted` — consent (party or contact_point), level, purposeCategory, channel
- `ConsentRevoked` — consent, level, purposeCategory, channel

**DNC Events:**

- `DncRuleCreated` — dncRule, party, scope
- `DncRuleRemoved` — dncRule, party, scope

**Relationship Events:**

- `RelationshipCreated` — relationship, fromParty, toParty
- `RelationshipUpdated` — relationship, changedAttributes
- `RelationshipEnded` — relationship (valid_to set)
- `RelationshipDeleted` — relationship

**Role Assignment Events:**

- `RoleAssignmentCreated` — roleAssignment, party, scopeParty
- `RoleAssignmentUpdated` — roleAssignment, changedAttributes
- `RoleAssignmentExpired` — roleAssignment (valid_to passed)
- `RoleAssignmentDeleted` — roleAssignment

**Resolver Events:**

- `ContactResolved` — request, result (includes matches and explanation)

---

## 10. Configuration

### 10.1 Configuration File

**File: `config/heyyou.php`**

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    |
    | All package tables will be prefixed with this value. Set to null or
    | empty string to disable prefixing.
    |
    */
    'table_prefix' => 'heyyou_',

    /*
    |--------------------------------------------------------------------------
    | Identifier Generator
    |--------------------------------------------------------------------------
    |
    | The class responsible for generating primary keys for package models.
    | Must implement \Vendor\HeyYou\Contracts\IdentifierGenerator.
    |
    */
    'identifier_generator' => \Vendor\HeyYou\Support\AutoIncrementGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Registries
    |--------------------------------------------------------------------------
    |
    | Bindings for the various registry contracts. Replace with your own
    | implementations (e.g., Taxon-backed registries).
    |
    */
    'registries' => [
        'channel' => \Vendor\HeyYou\Registries\ConfigChannelRegistry::class,
        'purpose' => \Vendor\HeyYou\Registries\ConfigPurposeRegistry::class,
        'role' => \Vendor\HeyYou\Registries\ConfigRoleRegistry::class,
        'relationship_type' => \Vendor\HeyYou\Registries\ConfigRelationshipTypeRegistry::class,
        'consent_category' => \Vendor\HeyYou\Registries\ConfigConsentCategoryRegistry::class,
        'normalizer' => \Vendor\HeyYou\Registries\DefaultNormalizerRegistry::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    */
    'verification' => [
        // Log verification attempts to verification_events table
        'log_history' => true,

        // Default expiration for verifications (null = never expires)
        'default_expiration_days' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Dispatcher
    |--------------------------------------------------------------------------
    |
    | The class responsible for dispatching package events.
    | Must implement \Vendor\HeyYou\Contracts\EventDispatcher.
    |
    */
    'event_dispatcher' => \Vendor\HeyYou\Events\LaravelEventDispatcher::class,

    /*
    |--------------------------------------------------------------------------
    | Default Channels
    |--------------------------------------------------------------------------
    |
    | Used by ConfigChannelRegistry. Ignored if using custom registry.
    |
    */
    'channels' => [
        'email' => ['name' => 'Email', 'category' => 'electronic'],
        'phone' => ['name' => 'Phone', 'category' => 'electronic'],
        'sms' => ['name' => 'SMS', 'category' => 'electronic'],
        'whatsapp' => ['name' => 'WhatsApp', 'category' => 'messaging'],
        'signal' => ['name' => 'Signal', 'category' => 'messaging'],
        'facebook' => ['name' => 'Facebook', 'category' => 'social'],
        'instagram' => ['name' => 'Instagram', 'category' => 'social'],
        'linkedin' => ['name' => 'LinkedIn', 'category' => 'social'],
        'twitter' => ['name' => 'Twitter/X', 'category' => 'social'],
        'tiktok' => ['name' => 'TikTok', 'category' => 'social'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Purposes
    |--------------------------------------------------------------------------
    |
    | Used by ConfigPurposeRegistry. Ignored if using custom registry.
    |
    */
    'purposes' => [
        'general' => ['name' => 'General', 'parent' => null],
        'billing' => ['name' => 'Billing', 'parent' => null],
        'accounts_payable' => ['name' => 'Accounts Payable', 'parent' => 'billing'],
        'accounts_receivable' => ['name' => 'Accounts Receivable', 'parent' => 'billing'],
        'shipping' => ['name' => 'Shipping', 'parent' => null],
        'receiving' => ['name' => 'Receiving', 'parent' => 'shipping'],
        'hr' => ['name' => 'Human Resources', 'parent' => null],
        'sales' => ['name' => 'Sales', 'parent' => null],
        'support' => ['name' => 'Support', 'parent' => null],
        'executive' => ['name' => 'Executive', 'parent' => null],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Roles
    |--------------------------------------------------------------------------
    |
    | Used by ConfigRoleRegistry. Ignored if using custom registry.
    |
    */
    'roles' => [
        'accounts_payable_contact' => ['name' => 'Accounts Payable Contact'],
        'accounts_receivable_contact' => ['name' => 'Accounts Receivable Contact'],
        'hr_contact' => ['name' => 'HR Contact'],
        'receiving_manager' => ['name' => 'Receiving Manager'],
        'shipping_contact' => ['name' => 'Shipping Contact'],
        'sales_contact' => ['name' => 'Sales Contact'],
        'support_contact' => ['name' => 'Support Contact'],
        'executive_contact' => ['name' => 'Executive Contact'],
        'primary_contact' => ['name' => 'Primary Contact'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Relationship Types
    |--------------------------------------------------------------------------
    |
    | Used by ConfigRelationshipTypeRegistry. Ignored if using custom registry.
    |
    */
    'relationship_types' => [
        'employment' => ['name' => 'Employment', 'from' => 'person', 'to' => 'organization'],
        'contractor' => ['name' => 'Contractor', 'from' => 'person', 'to' => 'organization'],
        'location_of' => ['name' => 'Location Of', 'from' => 'location', 'to' => 'organization'],
        'member_of' => ['name' => 'Member Of', 'from' => 'organization', 'to' => 'organization'],
        'parent_of' => ['name' => 'Parent Of', 'from' => 'organization', 'to' => 'organization'],
        'managed_by' => ['name' => 'Managed By', 'from' => 'location', 'to' => 'person'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Consent Categories
    |--------------------------------------------------------------------------
    |
    | Used by ConfigConsentCategoryRegistry. Ignored if using custom registry.
    |
    */
    'consent_categories' => [
        'transactional' => ['name' => 'Transactional'],
        'marketing' => ['name' => 'Marketing'],
        'support' => ['name' => 'Support'],
        'collections' => ['name' => 'Collections'],
    ],

];
```

### 10.2 Publishing Configuration

```bash
php artisan vendor:publish --tag=heyyou-config
```

---

## 11. Migrations

### 11.1 Migration Strategy

- Migrations auto-load by default via `loadMigrationsFrom()`
- Host apps can publish to customize: `vendor:publish --tag=heyyou-migrations`
- Published migrations take precedence (package checks for existence before loading)
- All migrations use table prefix helper
- Identifier columns use the configured generator

### 11.2 Migration Order

1. `create_heyyou_parties_table`
2. `create_heyyou_party_relationships_table`
3. `create_heyyou_contact_points_table`
4. `create_heyyou_contact_point_purposes_table`
5. `create_heyyou_addresses_table`
6. `create_heyyou_role_assignments_table`
7. `create_heyyou_party_consents_table`
8. `create_heyyou_contact_point_consents_table`
9. `create_heyyou_do_not_contacts_table`
10. `create_heyyou_verification_events_table`

### 11.3 Publishing Migrations

```bash
php artisan vendor:publish --tag=heyyou-migrations
```

---

## 12. Service Provider

```php
class HeyYouServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/heyyou.php', 'heyyou');

        // Bind identifier generator
        $this->app->bind(
            IdentifierGenerator::class,
            config('heyyou.identifier_generator')
        );

        // Bind registries
        $this->app->bind(ChannelRegistry::class, config('heyyou.registries.channel'));
        $this->app->bind(PurposeRegistry::class, config('heyyou.registries.purpose'));
        $this->app->bind(RoleRegistry::class, config('heyyou.registries.role'));
        $this->app->bind(RelationshipTypeRegistry::class, config('heyyou.registries.relationship_type'));
        $this->app->bind(ConsentCategoryRegistry::class, config('heyyou.registries.consent_category'));
        $this->app->bind(NormalizerRegistry::class, config('heyyou.registries.normalizer'));

        // Bind event dispatcher
        $this->app->bind(EventDispatcher::class, config('heyyou.event_dispatcher'));

        // Bind core services
        $this->app->bind(ContactResolver::class, DefaultContactResolver::class);
        $this->app->bind(DncChecker::class, DefaultDncChecker::class);
        $this->app->bind(ConsentChecker::class, DefaultConsentChecker::class);
        $this->app->bind(ScopeHierarchyResolver::class, RelationshipBasedScopeResolver::class);
        $this->app->bind(ContactPointPurposeManager::class, DefaultContactPointPurposeManager::class);
    }

    public function boot(): void
    {
        // Load migrations if not published
        if (!$this->migrationsPublished()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Publishables
        $this->publishes([
            __DIR__.'/../config/heyyou.php' => config_path('heyyou.php'),
        ], 'heyyou-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'heyyou-migrations');
    }

    private function migrationsPublished(): bool
    {
        return count(glob(database_path('migrations/*_create_heyyou_*.php'))) > 0;
    }
}
```

---

## 13. Directory Structure

```
heyyou/
├── config/
│   └── heyyou.php
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_heyyou_parties_table.php
│       ├── 2024_01_01_000002_create_heyyou_party_relationships_table.php
│       ├── ...
├── src/
│   ├── Contracts/
│   │   ├── IdentifierGenerator.php
│   │   ├── EventDispatcher.php
│   │   ├── ContactResolver.php
│   │   ├── DncChecker.php
│   │   ├── ConsentChecker.php
│   │   ├── ScopeHierarchyResolver.php
│   │   ├── ContactPointPurposeManager.php
│   │   ├── ChannelNormalizer.php
│   │   └── Registries/
│   │       ├── ChannelRegistry.php
│   │       ├── PurposeRegistry.php
│   │       ├── RoleRegistry.php
│   │       ├── RelationshipTypeRegistry.php
│   │       ├── ConsentCategoryRegistry.php
│   │       └── NormalizerRegistry.php
│   ├── Models/
│   │   ├── Party.php
│   │   ├── PartyRelationship.php
│   │   ├── ContactPoint.php
│   │   ├── ContactPointPurpose.php
│   │   ├── Address.php
│   │   ├── RoleAssignment.php
│   │   ├── PartyConsent.php
│   │   ├── ContactPointConsent.php
│   │   ├── DoNotContact.php
│   │   └── VerificationEvent.php
│   ├── Traits/
│   │   └── Contactable.php
│   ├── Events/
│   │   ├── LaravelEventDispatcher.php
│   │   ├── Party/
│   │   │   ├── PartyCreated.php
│   │   │   ├── ...
│   │   ├── ContactPoint/
│   │   │   ├── ContactPointCreated.php
│   │   │   ├── ...
│   │   └── ...
│   ├── Registries/
│   │   ├── ConfigChannelRegistry.php
│   │   ├── ConfigPurposeRegistry.php
│   │   ├── ConfigRoleRegistry.php
│   │   ├── ConfigRelationshipTypeRegistry.php
│   │   ├── ConfigConsentCategoryRegistry.php
│   │   └── DefaultNormalizerRegistry.php
│   ├── Normalizers/
│   │   ├── EmailNormalizer.php
│   │   ├── PhoneNormalizer.php
│   │   └── SmsNormalizer.php
│   ├── Resolver/
│   │   ├── DefaultContactResolver.php
│   │   ├── ResolverRequest.php
│   │   ├── ResolverResult.php
│   │   ├── ResolverMatch.php
│   │   ├── ResolverExplanation.php
│   │   └── ResolverConstraints.php
│   ├── Support/
│   │   ├── AutoIncrementGenerator.php
│   │   ├── TablePrefixer.php
│   │   └── ...
│   ├── Checkers/
│   │   ├── DefaultDncChecker.php
│   │   ├── DefaultConsentChecker.php
│   │   └── RelationshipBasedScopeResolver.php
│   └── HeyYouServiceProvider.php
├── tests/
│   └── ...
├── composer.json
└── README.md
```

---

## 14. Usage Examples

### 14.1 Basic Setup

```php
// User model in host app
class User extends Model
{
    use Contactable;

    public function getDisplayNameForParty(): string
    {
        return $this->full_name;
    }
}

// Company model in host app
class Company extends Model
{
    use Contactable;

    public function getDisplayNameForParty(): string
    {
        return $this->legal_name;
    }
}
```

### 14.2 Adding Contact Points

```php
$user = User::find(1);

// Add email
$user->party->contactPoints()->create([
    'channel' => 'email',
    'value_raw' => 'John.Doe@Example.com',
    'label' => 'Work Email',
    'is_primary' => true,
]);

// Add phone
$user->party->contactPoints()->create([
    'channel' => 'phone',
    'value_raw' => '(555) 123-4567',
    'label' => 'Mobile',
]);
```

### 14.3 Accessing Contact Points via Trait

```php
$user = User::find(1);

// Direct access
$user->contactPoints;
$user->contactPoints()->where('channel', 'email')->get();

// With eager loading
User::with('contactPoints')->get();
```

### 14.4 Creating Relationships

```php
$person = User::find(1)->party;
$company = Company::find(1)->party;

PartyRelationship::create([
    'from_party_id' => $person->id,
    'to_party_id' => $company->id,
    'relationship_type' => 'employment',
    'valid_from' => now(),
]);
```

### 14.5 Assigning Roles

```php
$person = User::find(1)->party;
$company = Company::find(1)->party;

RoleAssignment::create([
    'party_id' => $person->id,
    'scope_party_id' => $company->id,
    'role' => 'accounts_payable_contact',
    'priority' => 1,
]);
```

### 14.6 Resolving Contacts

```php
$company = Company::find(1);

$result = app(ContactResolver::class)->resolve(
    new ResolverRequest(
        targetParty: $company->party,
        purpose: 'accounts_payable',
        channel: 'email',
        constraints: new ResolverConstraints(
            requireVerified: true,
            requireConsent: false,
        ),
        limit: 5,
    )
);

if ($result->isEmpty()) {
    // No contacts found
    logger()->warning('No AP contact found', [
        'company' => $company->id,
        'exclusions' => $result->explanation->exclusionSummary,
    ]);
} else {
    $best = $result->best();
    
    // Send to: $best->normalizedValue
    // Via: $best->channel
    // Owner: $best->owningParty->display_name_cached
}
```

### 14.7 Managing Consent

```php
$contactPoint = ContactPoint::find(1);

// Grant consent at contact point level
ContactPointConsent::create([
    'contact_point_id' => $contactPoint->id,
    'purpose_category' => 'marketing',
    'status' => 'opted_in',
    'captured_at' => now(),
    'source' => 'web_form',
]);

// Revoke consent at party level
PartyConsent::create([
    'party_id' => $contactPoint->party_id,
    'channel' => 'sms',
    'purpose_category' => 'marketing',
    'status' => 'opted_out',
    'captured_at' => now(),
    'source' => 'user_request',
]);
```

### 14.8 Creating DNC Rules

```php
$party = User::find(1)->party;

// Block all marketing contact
DoNotContact::create([
    'party_id' => $party->id,
    'purpose' => 'marketing',
    'reason' => 'Customer requested no marketing',
    'source' => 'user_request',
    'effective_at' => now(),
]);

// Block specific contact point
DoNotContact::create([
    'party_id' => $party->id,
    'contact_point_id' => $contactPoint->id,
    'reason' => 'Number disconnected',
    'source' => 'system',
    'effective_at' => now(),
]);
```

---

## 15. Testing Considerations

### 15.1 Test Helpers

The package should provide:

- Factory classes for all models
- Trait for test cases: `use HeyYouTestHelpers`
- In-memory registry implementations for testing
- Resolver result assertions

### 15.2 Key Test Scenarios

- Party auto-creation via Contactable trait
- Contact point normalization per channel
- Deduplication constraint enforcement
- Consent precedence (contact-point > party)
- DNC blocking at various scopes
- Resolver ranking order
- Scope hierarchy traversal
- Effective dating filtering

---

## 16. Future Considerations (Out of Scope for v1)

- Address deduplication/sharing (Option C from decision)
- Configurable deduplication mode (loose vs strict)
- Resolver trace verbosity levels
- Batch resolution API
- Contact point merge utilities
- Import/export (vCard, CSV)
- Webhook integrations
- Rate limiting on contact attempts
