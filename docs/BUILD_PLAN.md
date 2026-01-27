# HeyYou Build Plan

This document outlines the incremental build plan for the HeyYou package using TDD.

## Build Chunks

Each chunk is designed to be completable in a single session with clear boundaries.

---

## Chunk 1: Foundation & Registry Contracts ✅ COMPLETE

**Goal:** Establish the foundational contracts, config, and support classes.

**Files to create:**
- `config/heyyou.php` - Configuration file
- `src/Contracts/IdentifierGenerator.php`
- `src/Contracts/Registries/RegistryItem.php`
- `src/Contracts/Registries/ChannelRegistry.php`
- `src/Contracts/Registries/PurposeRegistry.php`
- `src/Contracts/Registries/RoleRegistry.php`
- `src/Contracts/Registries/RelationshipTypeRegistry.php`
- `src/Contracts/Registries/ConsentCategoryRegistry.php`
- `src/Support/AutoIncrementGenerator.php`
- `src/Support/TablePrefixer.php`
- `src/Registries/ConfigChannelRegistry.php`
- `src/Registries/ConfigPurposeRegistry.php`
- `src/Registries/ConfigRoleRegistry.php`
- `src/Registries/ConfigRelationshipTypeRegistry.php`
- `src/Registries/ConfigConsentCategoryRegistry.php`

**Tests:**
- `tests/Unit/Support/AutoIncrementGeneratorTest.php`
- `tests/Unit/Support/TablePrefixerTest.php`
- `tests/Unit/Registries/ConfigChannelRegistryTest.php`
- `tests/Unit/Registries/ConfigPurposeRegistryTest.php`

**Completion criteria:** All registry contracts implemented, config file complete, tests pass.

---

## Chunk 2: Core Models (Party & Relationships) ✅ COMPLETE

**Goal:** Party model, Contactable trait, PartyRelationship model.

**Files to create:**
- `src/Models/Party.php`
- `src/Models/PartyRelationship.php`
- `src/Traits/Contactable.php`
- `database/migrations/2024_01_01_000001_create_heyyou_parties_table.php`
- `database/migrations/2024_01_01_000002_create_heyyou_party_relationships_table.php`
- `tests/Fixtures/Models/User.php` (test model)
- `tests/Fixtures/Models/Company.php` (test model)

**Tests:**
- `tests/Unit/Models/PartyTest.php`
- `tests/Unit/Traits/ContactableTest.php`
- `tests/Unit/Models/PartyRelationshipTest.php`
- `tests/Feature/ContactableTraitTest.php`

**Completion criteria:** Party auto-created via trait, relationships work, tests pass.

---

## Chunk 3: Contact Points & Normalization ✅ COMPLETE

**Goal:** ContactPoint model, normalizers, purpose management.

**Files to create:**
- `src/Models/ContactPoint.php`
- `src/Models/ContactPointPurpose.php`
- `src/Contracts/ChannelNormalizer.php`
- `src/Contracts/Registries/NormalizerRegistry.php`
- `src/Contracts/ContactPointPurposeManager.php`
- `src/Normalizers/EmailNormalizer.php`
- `src/Normalizers/PhoneNormalizer.php`
- `src/Normalizers/DefaultNormalizer.php`
- `src/Registries/DefaultNormalizerRegistry.php`
- `src/Support/DefaultContactPointPurposeManager.php`
- `database/migrations/2024_01_01_000003_create_heyyou_contact_points_table.php`
- `database/migrations/2024_01_01_000004_create_heyyou_contact_point_purposes_table.php`

**Tests:**
- `tests/Unit/Models/ContactPointTest.php`
- `tests/Unit/Normalizers/EmailNormalizerTest.php`
- `tests/Unit/Normalizers/PhoneNormalizerTest.php`
- `tests/Feature/ContactPointNormalizationTest.php`

**Completion criteria:** Contact points normalize on save, purposes attach/detach, tests pass.

---

## Chunk 4: Addresses & Role Assignments ✅ COMPLETE

**Goal:** Address model and RoleAssignment model with scopes.

**Files to create:**
- `src/Models/Address.php`
- `src/Models/RoleAssignment.php`
- `database/migrations/2024_01_01_000005_create_heyyou_addresses_table.php`
- `database/migrations/2024_01_01_000006_create_heyyou_role_assignments_table.php`

**Tests:**
- `tests/Unit/Models/AddressTest.php`
- `tests/Unit/Models/RoleAssignmentTest.php`
- `tests/Feature/RoleAssignmentScopesTest.php`

**Completion criteria:** Addresses work, role assignments with current() scope, tests pass.

---

## Chunk 5: Policy Layer (Consent & DNC) ✅ COMPLETE

**Goal:** Consent models, DNC model, verification events.

**Files to create:**
- `src/Models/PartyConsent.php`
- `src/Models/ContactPointConsent.php`
- `src/Models/DoNotContact.php`
- `src/Models/VerificationEvent.php`
- `src/Contracts/ConsentChecker.php`
- `src/Contracts/DncChecker.php`
- `src/Checkers/DefaultConsentChecker.php`
- `src/Checkers/DefaultDncChecker.php`
- `src/Support/ConsentResult.php`
- `src/Support/DncResult.php`
- `database/migrations/2024_01_01_000007_create_heyyou_party_consents_table.php`
- `database/migrations/2024_01_01_000008_create_heyyou_contact_point_consents_table.php`
- `database/migrations/2024_01_01_000009_create_heyyou_do_not_contacts_table.php`
- `database/migrations/2024_01_01_000010_create_heyyou_verification_events_table.php`

**Tests:**
- `tests/Unit/Checkers/DefaultConsentCheckerTest.php`
- `tests/Unit/Checkers/DefaultDncCheckerTest.php`
- `tests/Feature/ConsentPrecedenceTest.php`
- `tests/Feature/DncBlockingTest.php`

**Completion criteria:** Consent precedence works, DNC blocks correctly, tests pass.

---

## Chunk 6: Resolver Layer ✅ COMPLETE

**Goal:** Contact resolver with full algorithm.

**Files to create:**
- `src/Contracts/ContactResolver.php`
- `src/Contracts/ScopeHierarchyResolver.php`
- `src/Resolver/ResolverRequest.php`
- `src/Resolver/ResolverConstraints.php`
- `src/Resolver/ResolverResult.php`
- `src/Resolver/ResolverMatch.php`
- `src/Resolver/ResolverExplanation.php`
- `src/Resolver/DefaultContactResolver.php`
- `src/Checkers/RelationshipBasedScopeResolver.php`

**Tests:**
- `tests/Unit/Resolver/ResolverRequestTest.php`
- `tests/Unit/Resolver/DefaultContactResolverTest.php`
- `tests/Feature/ContactResolutionTest.php`
- `tests/Feature/ResolverRankingTest.php`
- `tests/Feature/ScopeHierarchyTest.php`

**Completion criteria:** Resolver returns ranked matches, respects policies, tests pass.

---

## Chunk 7: Events & Service Provider

**Goal:** Event system and final service provider wiring.

**Files to create:**
- `src/Contracts/EventDispatcher.php`
- `src/Events/LaravelEventDispatcher.php`
- `src/Events/Party/PartyCreated.php`
- `src/Events/Party/PartyUpdated.php`
- `src/Events/Party/PartyDeleted.php`
- `src/Events/ContactPoint/ContactPointCreated.php`
- `src/Events/ContactPoint/ContactPointUpdated.php`
- `src/Events/ContactPoint/ContactPointVerified.php`
- `src/Events/Consent/ConsentGranted.php`
- `src/Events/Consent/ConsentRevoked.php`
- `src/Events/Dnc/DncRuleCreated.php`
- `src/Events/Resolver/ContactResolved.php`
- (Additional events as needed)

**Updates:**
- `src/HeyYouServiceProvider.php` - Complete all bindings

**Tests:**
- `tests/Unit/Events/EventDispatcherTest.php`
- `tests/Feature/EventDispatchingTest.php`
- `tests/Feature/ServiceProviderTest.php`

**Completion criteria:** Events dispatch correctly, all contracts bound, tests pass.

---

## Chunk 8: Integration Tests & Polish ✅ COMPLETE

**Goal:** End-to-end integration tests, factories, final polish.

**Files to create:**
- `database/factories/PartyFactory.php`
- `database/factories/ContactPointFactory.php`
- `database/factories/AddressFactory.php`
- `database/factories/RoleAssignmentFactory.php`

**Tests:**
- `tests/Feature/Integration/FullWorkflowTest.php`
- `tests/Feature/Integration/MultiTenantScenarioTest.php`

**Updates:**
- Update `CLAUDE.md` with final architecture documentation
- Ensure all PHPStan level 8 passes
- Ensure Pint formatting passes

**Completion criteria:** Full workflow tests pass, quality checks pass.

---

## How to Resume

When starting a new chunk, tell Claude:

> "Continue building HeyYou package. Start Chunk N. Read docs/spec.md for the full specification and docs/BUILD_PLAN.md for the build plan."

Claude should:
1. Read the spec and build plan
2. Check what exists (tests, src files)
3. Continue from where the previous chunk left off
4. Use TDD: write tests first, then implementation
5. Run `ddev test` to verify tests pass before completing
