# Implementation Queue

> Deferred work, captured mid-session, picked up deliberately. Managed by `/queue`;
> convention: `$CLAUDE_HARNESS_DIR/notes/implementation-queue.md`. Hand-editing is fine.

## Queued

### Contactable consumers with integer primary keys cannot use this package on PostgreSQL
- **Added**: 2026-08-07 · found while widening the constraint matrix (branch `chore/pest5-phpunit13-php84-matrix`)
- **Tier**: LIGHT
- **Why deferred**: out of scope for a constraint-widening task; needs a design decision, not a patch
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

### Push the branch and cut a release tag
- **Added**: 2026-08-07 · ccstake adopts this package in its identity-unification build
- **Tier**: SOLO
- **Why deferred**: gated on the two items above
- **Context**: `chore/pest5-phpunit13-php84-matrix` is green but unpushed and untagged, so
  no consumer can see any of it. ccstake requires a tag (or an explicit `@dev` pin) before
  build 1 can adopt hey-you. The only active consumer is **afwd** (locked v1.2.0; it has an
  uncommitted QUEUE.md note warning about the `$incrementing` change). larquacious is the
  other consumer and is being abandoned — skip it. nbss does not consume this package.
  Land H1 and H2 first so the tag carries them (H3).

## Blocked

## Archive

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
