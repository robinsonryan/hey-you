# Implementation Queue

> Deferred work, captured mid-session, picked up deliberately. Managed by `/queue`;
> convention: `$CLAUDE_HARNESS_DIR/notes/implementation-queue.md`. Hand-editing is fine.

## Queued

### `$user->contactPoints` and `$user->addresses` are documented but have never existed
- **Added**: 2026-08-08 · surfaced while writing the integer-key consumer tests
- **Tier**: LIGHT if built, SOLO if the claim is withdrawn
- **Why deferred**: needs a call on which way to reconcile — the docs promise an API
  that the trait never had, and the two resolutions differ by roughly a day
- **Context**: `Contactable` defines exactly one relation, `party()`. The docs promise
  two more:
  - `$user->contactPoints` / `$user->contactPoints()->where('channel', 'phone')->get()`
    — `docs/contact-points.md` (7 mentions), `docs/spec.md` §3.2
  - `$user->addresses` / `$company->addresses()->where('purpose', 'billing')->get()`
    and `Company::with('addresses')` — `docs/addresses.md` §"Via Consumer Model"

  Calling either raises `BadMethodCallException: Call to undefined method
  ...::contactPoints()`. Confirmed against a live model while writing
  `ContactableIntegerKeyTest`, not read off the source. CLAUDE.md and `docs/spec.md`
  now point readers at `$user->party->contactPoints` in the meantime; the two doc
  files above still make the bare claim, pending this decision.
- **Decide**: build both relations, or delete the claim from `docs/contact-points.md`
  and `docs/addresses.md`.
- **Measured 2026-08-08** with a throwaway `hasManyThrough` probe against real
  PostgreSQL (two consumer models, one UUID-keyed and one bigint-keyed), rather than
  estimated from reading. What a plain `hasManyThrough(ContactPoint::class,
  Party::class, 'partyable_id', 'party_id', $localKey, 'id')` does:
  - ✅ Single-model read works on **both** key types — the outer key is a bound value,
    and a lone bound integer coerces against varchar.
  - ✅ `->where('heyyou_parties.partyable_type', static::class)` does land in the SQL,
    so the morph-type constraint needs no special machinery. (An earlier note here
    claimed otherwise; the probe disproved it.)
  - ❌ **Eager loading** an integer-keyed consumer fails —
    `whereIntegerInRaw` inlines `in (2)` past the binding layer.
  - ❌ **`has()` / `whereHas()`** on an integer-keyed consumer fails — the correlated
    comparison `legacy_accounts.id = heyyou_parties.partyable_id` is column-to-column,
    so there is no binding to coerce.

  The two failures are exactly two of the three that `PartyMorphOne` already solves,
  so the fix is the same pair of overrides (`whereInMethod()`, plus a
  `cast(... as varchar)` on the qualified parent key) hoisted into a shared concern
  and applied to a `HasManyThrough` subclass.
- **Cost**: *Build* ≈ half a day — one shared concern + one relation subclass in
  `src/Relations/`, two trait methods, and tests covering both key types × both broken
  paths × two relations, plus a morph-type collision case. *Withdraw* ≈ 15 minutes,
  ~6 lines across two files, at the price of an affordance the spec calls headline.

### `config('heyyou.identifier_generator')` is bound to nothing that reads it
- **Added**: 2026-08-08 · found while correcting the identifier-strategy docs
- **Tier**: SOLO
- **Context**: `HeyYouServiceProvider::registerIdentifierGenerator()` binds the
  configured class to the `IdentifierGenerator` contract, and `ServiceProviderTest`
  asserts the binding resolves — but **nothing in the package ever resolves it**.
  `columnDefinition()` has no caller at all (the migrations write
  `$table->uuid('id')->primary()->default(DB::raw('uuidv7()'))` directly), and the one
  caller of `generate()` — `PartyFactory` — instantiates `Uuid7Generator` by hand
  rather than going through the container. Setting the config to
  `AutoIncrementGenerator` therefore changes nothing, which is a worse failure than an
  error: the knob turns and the machine ignores it.
- **Decide**: wire the migrations through `columnDefinition()` so the contract is real,
  or delete the config key, the contract, and `AutoIncrementGenerator` and keep
  `Uuid7Generator` as a plain utility. Leaning toward deletion — the package mandates
  PostgreSQL 18 and UUID7 everywhere, so a pluggable key strategy is a door into a room
  that no longer exists. The docs now say plainly that the setting has no effect.

## Blocked

## Archive

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
