# Implementation Queue

> Deferred work, captured mid-session, picked up deliberately. Managed by `/queue`;
> convention: `$CLAUDE_HARNESS_DIR/notes/implementation-queue.md`. Hand-editing is fine.

## Queued

### `$user->contactPoints` is documented everywhere but has never existed
- **Added**: 2026-08-08 · surfaced while writing the integer-key consumer tests
- **Tier**: SOLO
- **Why deferred**: out of scope for the integer-key fix; needs a call on which way to
  reconcile, and the release was already gated on two other items
- **Context**: `docs/contact-points.md`, `docs/spec.md` (§ "Consumer models access
  contact data directly via trait methods") and the old CLAUDE.md all show
  `$user->contactPoints` / `$user->contactPoints()->where(...)`. The `Contactable`
  trait only ever defined `party()` — calling it raises
  `BadMethodCallException: Call to undefined method ...::contactPoints()`. Confirmed
  against a real model, not read off the source.
- **Decide**: add a `HasManyThrough` (consumer → party → contact points) to the trait
  and keep the docs, or delete the claim from all three places. The spec treats it as
  a headline affordance, which argues for adding it. CLAUDE.md now points readers at
  `$user->party->contactPoints` in the meantime.

### `docs/installation.md` still claims auto-incrementing integer primary keys
- **Added**: 2026-08-08 · same pass
- **Tier**: SOLO
- **Context**: the "Custom Identifier Strategy" section opens "By default, HeyYou uses
  auto-incrementing integers for primary keys" and offers a `Str::uuid()` generator as
  the upgrade path. Since `d1e1829` every package table has been UUID7 with a
  PostgreSQL `uuidv7()` column default, and CLAUDE.md forbids exactly the pattern the
  doc recommends. A reader following it lands in the failure mode the identifier
  conventions exist to prevent.
- **Decide**: rewrite the section around the `uuidv7()` default and describe what
  `identifier_generator` actually still controls (pre-persist IDs via
  `Uuid7Generator`), or drop it.

## Blocked

## Archive

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
