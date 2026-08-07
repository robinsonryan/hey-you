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
  Decide: (a) document UUID PKs as a hard requirement for `Contactable`, or (b) cast
  the morph key to string in the trait so bigint consumers work.

### Rector 2 proposes 61 files of closure `: void` return types
- **Added**: 2026-08-07 · same session
- **Tier**: SOLO
- **Why deferred**: pure style churn, not in the `composer quality` gate, and it would
  have swamped the diff of a constraint change
- **Context**: `rector/rector` moved 1.2 → 2.6 with the Laravel 13 bump. Its
  `TYPE_DECLARATION` set gained `AddClosureVoidReturnTypeWhereNoReturnRector`, so
  `composer refactor:check` now reports 61 files. Run `composer refactor` as its own
  commit.

## Blocked

## Archive

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
