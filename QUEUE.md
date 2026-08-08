# Implementation Queue

> Deferred work, captured mid-session, picked up deliberately. Managed by `/queue`;
> convention: `$CLAUDE_HARNESS_DIR/notes/implementation-queue.md`. Hand-editing is fine.

## Queued

### Verification history can adopt an arbitrarily old pending attempt
- **Added**: 2026-08-08 · narrowed but deliberately not closed during the half-wired-features build
- **Tier**: SOLO
- **Context**: `ContactPoint::markVerified()` completes an open `pending` row rather than
  opening a second one. Review found it was adopting rows started under a *different*
  method, so a `link` attempt could be completed by a `manual` verification and the
  history row would then contradict both the model and the dispatched event. That is
  fixed — adoption now requires a matching method.

  What remains: because `markVerificationFailed()` deliberately leaves its pending row
  open (the pending row models the *attempt*; failed rows are individual rejected
  tries), a same-method pending row from years ago is still eligible for adoption. The
  resulting row would carry a stale `initiated_at` with a current `completed_at`.
- **Why deferred**: any cutoff is invented policy. The spec says nothing about how long
  an attempt stays open, and picking "24 hours" or "30 days" in a package that cannot
  know the host's verification flow is worse than leaving the seam visible.
- **Decide**: a `verification.pending_ttl_hours` config key, or have
  `markVerificationFailed()` close its pending row as failed rather than leaving it
  open, or accept adoption-regardless-of-age as correct ("the attempt eventually
  succeeded") and document it.

### The implicit verification path publishes a `verified_at` it never persists
- **Added**: 2026-08-08 · found by the remediation pass, outside its brief
- **Tier**: SOLO
- **Context**: when a host assigns `is_verified = true` and saves without setting
  `verified_at`, the `updated` hook computes `verified_at ?? now()` for the dispatched
  event and the history row — but never writes that value back to the column. The model
  is left at `is_verified = true, verified_at = null` while the event and the audit row
  both carry a concrete timestamp. Harmless today (`isCurrentlyVerified()` is true and
  nothing contradicts), but it is the same species as the expiry-timestamp fiction that
  was just fixed: a value published to the outside world that the row does not hold.
- **Decide**: persist the derived `verified_at` back onto the model in the hook, or
  document the implicit path as lossy and point people at `markVerified()`.

## Blocked

## Archive

### `$user->contactPoints` and `$user->addresses` are documented but have never existed
- **Added**: 2026-08-08 · surfaced while writing the integer-key consumer tests
- **Tier**: LIGHT — built as module M1 of a FULL-tier build
- **Context**: `docs/spec.md` §3.2 promised both; `docs/contact-points.md` and
  `docs/addresses.md` showed them in use; `Contactable` defined only `party()`, so every
  documented call raised `BadMethodCallException`. Confirmed against a live model.
- **Done**: 2026-08-08 · `src/Relations/PartyHasManyThrough.php` plus
  `src/Relations/Concerns/CoercesConsumerKeyToText.php`, hoisted out of `PartyMorphOne`
  so both relation types share one copy of the key-coercion logic. Both relations
  constrain `partyable_type` inside `addConstraints()` rather than at the call site, so
  the isolation guarantee is structural. Proven by forcing two bigint-keyed consumers
  onto id 4242 and asserting the collision before asserting isolation; deleting the
  constraint turns five tests red, one a clean cross-tenant read. Every form the docs
  already showed now works verbatim — no doc corrections were needed for those two files.
- **Correction recorded**: the pre-build estimate here claimed a plain `hasManyThrough`
  "cannot constrain `partyable_type`". A probe disproved it — a chained `->where()` does
  survive into `has()` via `mergeConstraintsFrom`. The constraint moved into the relation
  for defensibility, not necessity.

### `config('heyyou.identifier_generator')` is bound to nothing that reads it
- **Added**: 2026-08-08 · found while correcting the identifier-strategy docs
- **Tier**: SOLO — built as the foundation commit of the same build
- **Context**: the provider bound the configured class to the `IdentifierGenerator`
  contract and a test asserted the binding resolved, but nothing ever resolved it.
  `columnDefinition()` had no caller; the sole caller of `generate()` built
  `Uuid7Generator` by hand. The knob turned and the machine ignored it.
- **Done**: 2026-08-08 · retired. Contract, `AutoIncrementGenerator`, config key and
  binding all removed; `Uuid7Generator` kept as a plain class for pre-persist IDs and
  given the test coverage it never had. Recorded in the CHANGELOG as a **deviation from
  spec §2.2 and §11.1** — genuine pluggability is assumed away in four places (12
  `foreignUuid()` columns the contract has no method for, `ConfiguresIdentifiers`
  hardcoding `$keyType = 'string'`, `partyable_id` being varchar, and the relation cast
  keying off `getKeyType()`), and restoring it would mean re-opening database
  portability that PostgreSQL-18-only `uuidv7()` rules out.

### Verification history was scaffolded and never wired
- **Added**: 2026-08-08 · found auditing the spec against `src/` for other half-built features
- **Tier**: LIGHT — built as module M3 of the same build
- **Context**: the `heyyou_verification_events` table, the `VerificationEvent` model,
  `ContactPoint::verificationEvents()` and both `verification.*` config keys all existed.
  Nothing ever wrote a row; the only code creating one was a test. Relatedly,
  `ContactPointVerificationFailed` and `ContactPointVerificationExpired` were the only
  two of the package's 35 event classes with no dispatch site anywhere.
- **Done**: 2026-08-08 · four intent methods on `ContactPoint` — `startVerification()`,
  `markVerified()`, `markVerificationFailed()`, `markVerificationExpired()`. Explicit
  methods rather than hooks because a failure is not an attribute change, which is
  precisely why those two events were unreachable. Both config keys now do something.
  Review caught three defects, all fixed: cross-method pending adoption forging the audit
  trail, expiry leaving stale timestamps that a later re-verify republished, and expiring
  a never-verified point inventing history. Two follow-ups remain queued above.

### `docs/installation.md` still claimed auto-incrementing integer primary keys
- **Added**: 2026-08-08 · found while writing the integer-key consumer tests
- **Tier**: SOLO
- **Context**: the "Custom Identifier Strategy" section opened "By default, HeyYou uses
  auto-incrementing integers for primary keys" and offered a `Str::uuid()` generator as
  the upgrade path. Since `d1e1829` every package table has been UUID7 with a
  PostgreSQL `uuidv7()` column default, and CLAUDE.md forbids exactly the pattern the
  doc recommended. A reader following it landed in the failure mode the identifier
  conventions exist to prevent.
- **Done**: 2026-08-08 · the drift was in three files, not one. `docs/installation.md`
  §"Custom Identifier Strategy" is now §"Primary Keys", describing the `uuidv7()`
  default, why `$incrementing = true` is correct, why `HasUuids` is not, and the narrow
  pre-persist role `Uuid7Generator` actually plays. `docs/configuration.md` named
  `AutoIncrementGenerator` as the default in two places (both corrected, both now state
  the setting does not control primary keys). `docs/spec.md` is the original design
  document — namespace `Vendor\HeyYou`, database-agnostic, `$table->id()` keys — so it
  carries a divergence table at the top rather than a rewrite.

### Contactable consumers with integer primary keys cannot use this package on PostgreSQL (H2)
- **Added**: 2026-08-07 · found while widening the constraint matrix (branch `chore/pest5-phpunit13-php84-matrix`)
- **Tier**: LIGHT (executed SOLO — one source file plus fixtures and tests)
- **Why it was deferred**: out of scope for a constraint-widening task; needed a design
  decision, not a patch
- **Context**: `heyyou_parties.partyable_id` is `varchar`. A consumer model with a
  bigint PK binds `$model->getKey()` as an integer, and PostgreSQL refuses
  `varchar = integer` (`SQLSTATE[42883] operator does not exist`) — so the
  `Contactable` trait's `morphOne` blows up on every lookup. SQLite silently coerced,
  which is why this was never seen. Every model in this package already mandates UUID7
  keys, and the test fixtures were moved onto UUID keys to match, so the documented
  path works; the gap is only for consumers who did not adopt the UUID convention.
- **DECIDED 2026-08-07 (Ryan): option (b) — cast the morph key.** Keep the
  `IdentifierGenerator` contract configurable; cast `partyable_id` to string in the
  `Contactable` trait so bigint consumers work on PostgreSQL. Add tests that exercise a
  bigint-keyed consumer **against Postgres** so this cannot silently regress — the
  original bug survived only because SQLite coerced the mismatch. Prerequisite for the
  release tag; see ccstake `docs/plans/identity-unification-spec.md` §4 (H2).
- **Done**: 2026-08-08 · `src/Relations/PartyMorphOne.php`, returned by
  `Contactable::party()`. Three distinct leaks, not one — a probe fixture found them
  before any fix was written, and only the first was the one predicted:
  1. bound values (`getParentKey()`, and `getKeys()` for eager loads) → cast to string;
  2. Eloquent optimises an eager load on an integer PK into `whereIntegerInRaw`, which
     inlines bare integers into the SQL **past the binding layer** — `whereInMethod()`
     is overridden to force the ordinary bound `whereIn`;
  3. `has()` / `whereHas()` compare column-to-column with no binding at all —
     `getQualifiedParentKeyName()` returns a `cast(... as varchar)` expression, and
     only when `$parent->getKeyType() !== 'string'`, so UUID consumers' SQL is
     untouched.
  Covered by `tests/Unit/Traits/ContactableIntegerKeyTest.php` (8 cases against real
  PostgreSQL, via a `LegacyAccount` fixture on `$table->id()`): create, fresh read,
  eager load, `has()`, display-name sync, soft delete, reverse `partyable` morph, and
  reaching contact points. Every one of the three failed before the fix.

### Push the branch and cut a release tag (H3)
- **Added**: 2026-08-07 · ccstake adopts this package in its identity-unification build
- **Tier**: SOLO
- **Why it was deferred**: gated on H1 and H2
- **Context**: `chore/pest5-phpunit13-php84-matrix` was green but untagged, so no
  consumer could see any of it. ccstake requires a tag (or an explicit `@dev` pin)
  before build 1 can adopt hey-you. larquacious is the other consumer and is being
  abandoned — skip it. nbss does not consume this package.
- **Done**: 2026-08-08 · merged to `main` and tagged **v0.1.2** carrying H1, H2 and the
  constraint matrix. Note the earlier retag (what was `v1.2.0` became `v0.1.2`); the
  CHANGELOG's stale "v1.2.0" reference was corrected to v0.1.1, which is the tag that
  actually narrowed php to `^8.3`. **afwd pins `^0.1.1` and will pick v0.1.2 up on its
  next `composer update`** — it carries the `ConfiguresIdentifiers` `$incrementing`
  change, which afwd's own QUEUE note already flags.

### Rector 2 proposes 61 files of closure `: void` return types (H1)
- **Added**: 2026-08-07 · same session as the constraint-matrix work
- **Tier**: SOLO
- **Why it was deferred**: pure style churn, not in the `composer quality` gate, and it
  would have swamped the diff of a constraint change.
- **Context**: `rector/rector` moved 1.2 → 2.6 with the Laravel 13 bump. Its
  `TYPE_DECLARATION` set gained `AddClosureVoidReturnTypeWhereNoReturnRector`, so
  `composer refactor:check` reported 61 files. The root cause was cross-package: the
  `vendor-pkg` template ships four tool configs and an empty `scripts` block, so **all
  eight** packages inherited configs with no gate.
- **Done**: 2026-08-07 · shipped as item **P5** of
  `$CLAUDE_HARNESS_DIR/notes/package-quality-baseline-spec.md`. `@refactor:check` is now
  composed into the `quality` script, so the drift cannot re-accumulate unseen. The
  backlog was exactly the predicted 61 files, cleared in the same commit. Eight rules
  fired, not one: the closure `: void` rule on 47 files plus `ReadOnlyClassRector` (9
  DTO/service classes), `FlipTypeControlToUseExclusiveTypeRector` (5), `ClosureToArrow
  FunctionRector` (2), `RemoveNullNamedArgOnNullDefaultParamRector` (2),
  `RemoveDeadConditionAboveReturnRector`, `ReadOnlyPropertyRector` and
  `ClassOnObjectRector` (1 each). All behaviour-preserving — see CHANGELOG. Note Rector
  needed **two passes** to reach a fixed point (`ClosureToArrowFunctionRector` output
  fed `AddArrowFunctionReturnTypeRector`), and Pint had to run after each.
  `phpstan.neon` also gained `phpVersion: 80200` and `tmpDir: .phpstan.cache` per the
  baseline. Still a prerequisite for the release tag (H3).

### Support Pest 5 / PHPUnit 13 / PHP 8.4+ in the constraint matrix
- **Added**: 2026-08-07 · harness health & efficiency session — apps are queued to upgrade to Pest 5 for Tia; consuming apps can't move until this package allows it
- **Tier**: SOLO
- **Done**: 2026-08-07 · branch `chore/pest5-phpunit13-php84-matrix`. Final matrix:
  php `^8.2` (widened back down — v1.2.0 had narrowed to `^8.3`, which locked out
  PHP 8.2 consumers), illuminate `^11|^12|^13` unchanged, testbench `^9|^10|^11`,
  pest `^3|^4|^5`, pest-plugin-laravel `^3|^4|^5`, phpunit `^11|^12|^13`. Resolves on
  PHP 8.4 to Pest 5.0.4 / PHPUnit 13.2.6 / Laravel 13.24 / testbench 11.1.
  `ddev quality` green: Pint 145 files, PHPStan level 8 clean, **287 tests / 577
  assertions in ~23 s**. Getting there also meant fixing a suite that had been 100% red
  since `d1e1829` (Postgres-only DDL vs. SQLite test connection) and a null-primary-key
  bug in `ConfiguresIdentifiers` — see CHANGELOG.
