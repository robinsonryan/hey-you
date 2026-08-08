# Finish the half-wired features

**Date:** 2026-08-08 · **Tier:** FULL (three modules, two of which touch `config/heyyou.php`)
**Baseline:** `471b59f` on `main`, v0.1.2 tagged.

## Why this exists

An audit of `docs/spec.md` and `docs/BUILD_PLAN.md` against `src/` found the package
structurally complete — 10/10 models, 10/10 migrations, all 10 spec'd contracts, 35 event
classes, resolver matching its contract — with exactly three loose wires: config keys and
documented APIs pointing at machinery that was never connected, or was disconnected later
and left standing.

`BUILD_PLAN.md` Chunk 7 is unmarked but its deliverables all exist and its tests pass; the
build was not abandoned mid-way. These three are the whole gap.

## Application-Wide Design Check (reuse before building)

| New surface | Existing capability it consumes | Not reinvented |
|---|---|---|
| `Contactable::contactPoints()` / `addresses()` | `Party::contactPoints()` / `Party::addresses()` (`src/Models/Party.php:155,163`) — the through-relation hops the existing `HasMany`, it does not re-query contact points | A second contact-point query path |
| Integer-key coercion for the through-relations | `PartyMorphOne` (`src/Relations/PartyMorphOne.php`) already solves this for the direct morph; its two overrides get hoisted into a shared concern | A second, divergent copy of the cast logic |
| Verification history writes | `VerificationEvent` model + `heyyou_verification_events` table + `ContactPoint::verificationEvents()`, all already present and unused | A new audit table |
| Verification lifecycle signalling | `ContactPointVerificationFailed` / `ContactPointVerificationExpired`, both already defined and never dispatched | New event classes |

## Requirements ledger

| ID | Requirement (source) | Implementation | Tests | Status |
|---|---|---|---|---|
| R1 | `Contactable` provides `contactPoints(): HasManyThrough` (spec §3.2; `docs/contact-points.md`) | M1 | M1 | done — `Contactable::contactPoints()`, `PartyHasManyThrough` |
| R2 | `Contactable` provides `addresses(): HasManyThrough` (spec §3.2; `docs/addresses.md` "Via Consumer Model") | M1 | M1 | done — `Contactable::addresses()` |
| R3 | Both relations constrain `partyable_type` — a `User` and a `Company` sharing a key value must not read each other's rows | M1 | M1 | done — constrained in `addConstraints()`; mutation kills 5 tests |
| R4 | Both relations work for integer-keyed consumers on eager load and `has()`/`whereHas()` (the two paths measured broken 2026-08-08) | M1 | M1 | done — plus a native-`uuid` case the brief did not predict |
| R5 | `verification.log_history` enables/disables verification event logging (spec §4.4) | M3 | M3 | done — both settings tested; events still fire when logging is off |
| R6 | A `VerificationEvent` row records status/method/evidence/initiated_at/completed_at/expires_at (spec §4.4 column table) | M3 | M3 | done |
| R7 | `ContactPointVerificationFailed` and `ContactPointVerificationExpired` are reachable — currently the only two event classes in the package with no dispatch site | M3 | M3 | done — both events now dispatched |
| R8 | `verification.default_expiration_days` has a defined effect or does not exist (not in spec; currently inert) | M3 | M3 | done — applies only when no explicit expiry is passed |
| R9 | `identifier_generator` either governs identifier columns (spec §11.1) or is retired — it must not remain a config key that silently does nothing | M2 | M2 | done — retired (deviation, recorded) |
| N1 | **Non-goal:** the package does not become database-portable. PostgreSQL 18+ stays a hard requirement (`uuidv7()`). | — | — | by design |
| N2 | **Non-goal:** consumer-side primary key strategy is not the package's business. Integer-keyed consumers are supported (v0.1.2); the package's own tables stay UUID7. | — | — | by design |
| N3 | **Non-goal:** no verification *transport* (sending codes/links). Spec §1.2 — "not a messaging provider." The package records verification outcomes; the host app performs them. | — | — | by design |

## Decision log

- **D1 — `verification.log_history`: build the writer, do not delete the table.** Spec §4.4
  defines it as a feature; the schema, model and relation already exist; the change is
  additive. Deleting would mean shipping a destructive migration that consumers inherit and
  cannot easily walk back. *Assumption stated to Ryan before building; stop condition if wrong.*
- **D2 — retire `identifier_generator` rather than wire it up.** This is a **deviation from
  spec §2.2 and §11.1** ("Identifier columns use the configured generator") and is recorded
  as such. Rationale: `d1e1829` replaced contract-driven column definitions with hardcoded
  `uuidv7()` DDL and left the contract orphaned. Genuine pluggability is assumed away in four
  places — 12 `foreignUuid()` columns the contract has no method for, `ConfiguresIdentifiers`
  hardcoding `$keyType = 'string'`, `partyable_id` being varchar, and `PartyMorphOne`'s cast
  keying off `getKeyType()`. Restoring it would mean re-opening database portability, which N1
  rejects.
- **D3 — verification state changes get an explicit API.** `ContactPointVerificationFailed`
  cannot be dispatched from an `updated` model hook, because a failure is not an attribute
  change. Explicit intent methods are therefore required for R7, not a stylistic preference.
- **D4 — `default_expiration_days` applies only when no explicit expiry is given** at the
  moment of verification. `null` (today's value) means "no expiry," preserving current behavior.
- **D5 — the existing implicit path keeps working.** Directly setting `is_verified = true` and
  saving must continue to dispatch `ContactPointVerified` and must also log history. Backward
  compatibility for anyone on v0.1.2.

## Module ownership — every file has exactly one owner

**Foundation (orchestrator, committed before any fan-out):** M2. It is the only module that
touches `config/heyyou.php` and `HeyYouServiceProvider.php`, and it is deletion-shaped, so
landing it first removes the sole ownership conflict.

| Module | Owns | Load-bearing invariant |
|---|---|---|
| **M2 — retire identifier_generator** (foundation, solo) | `config/heyyou.php`, `src/Contracts/IdentifierGenerator.php`, `src/Support/AutoIncrementGenerator.php` (delete), `src/Support/Uuid7Generator.php`, `src/HeyYouServiceProvider.php`, `tests/Feature/ServiceProviderTest.php`, `tests/Unit/Support/AutoIncrementGeneratorTest.php` (delete) | No behavior changes. The DDL every migration emits is byte-for-byte identical before and after. |
| **M1 — consumer relations** | `src/Relations/*`, `src/Traits/Contactable.php`, `tests/Unit/Traits/*`, `tests/Fixtures/Models/*` | A consumer never sees another consumer type's rows, even when their primary key values collide. |
| **M3 — verification history** | `src/Models/ContactPoint.php`, `src/Models/VerificationEvent.php`, `tests/Unit/Models/ContactPointTest.php`, new verification test files | History is written only when `log_history` is true, and no verification path writes a row without dispatching its matching event. |

**Frozen — no module touches these:** `database/migrations/**` (no schema change in this
build; the verification table already has every column spec §4.4 requires), `src/Resolver/**`,
`src/Checkers/**`, `src/Registries/**`, `src/Normalizers/**`.

**Orchestrator-owned at merge (no builder writes these):** `CHANGELOG.md`, `QUEUE.md`,
`CLAUDE.md`, `docs/**` outside this plan file.

## Sequence

1. Foundation: M2 solo → full gate → commit.
2. Worktrees at `.claude/worktrees/{relations,verification}`, per-tree `testing_wt_*` database
   via `HEYYOU_TEST_DB_DATABASE`. Verify one isolated test run before launching builders.
3. M1 and M3 build in parallel (TDD, module-scoped suites).
4. Merge to feature branch → **full** `ddev quality`.
5. One reviewer per module against the six-dimension charter, each briefed with the invariant
   above. Dimension 6 (UX/lexicon) is N/A — this package has no user-facing surface; reviewers
   are told so explicitly rather than left to invent findings.
6. Single remediation agent.
7. Full gate → teardown → completion audit against this ledger.
