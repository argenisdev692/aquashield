# Backups — ARCHITECTURE.md (PROGRESSIVE variant, only with ≥2 triggers)

> Copy of the contract in `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`.
> The mode line is parsed by `tests/Architecture/ModuleComplexityTest.php`.

mode: PROGRESSIVE

## Why PROGRESSIVE (2 of 4 triggers met — honestly)

- [ ] **≥3 models with business rules** — not met. There is no first-party
      model at all. The archive inventory is the filesystem; spatie owns the
      dump format and the filename. Inventing a `backup_archives` table would
      create a second source of truth that drifts the moment `backup:clean`
      runs.
- [x] **Invariants** — two rules a panel must never violate:
      (1) the newest active archive is never removed, so the system cannot be
      left without a recent recovery point;
      (2) a quarantined archive only returns when its destination filename is
      free, so a restore never overwrites a newer dump.
      Enforced by `Domain/Services/BackupLifecycleGuard.php` and covered by a
      DB-free unit test.
- [x] **Tests must substitute the external** — a database dump shells out to
      `mysqldump`/`pg_dump` and writes zip archives to a disk; the panel's
      listing reads that disk. Without `BackupArchiveStore` and
      `DatabaseBackupRunner` the feature tests would either run real dumps or
      skip, so the ports exist because a test substitutes them, not as
      decoration.
- [ ] **Extraction roadmap** — no. This is an operational panel, not a
      candidate service.

The module is PROGRESSIVE on 2/4. Trigger 3 alone would be weak (every
integration has an external), but combined with the deletion invariant it is
the honest minimum. If either stop being true, demote.

## Boundaries and public contracts

- Web routes: `GET /backups`, `GET /backups/{uuid}` (Inertia + JSON).
- REST routes (Sanctum, read-only, Scramble-documented): `GET /api/backups`,
  `GET /api/backups/{uuid}`, `GET /api/backups/{uuid}/download`.
- Permissions: `VIEW_ANY_BACKUPS`, `VIEW_BACKUPS`, `DOWNLOAD_BACKUPS`,
  `CREATE_BACKUPS`, `DELETE_BACKUPS`, `RESTORE_BACKUPS`,
  `BULK_DELETE_BACKUPS`, `BULK_RESTORE_BACKUPS`, `EXPORT_BACKUPS`.
- Response shapes: `Data\BackupArchiveData`, `Data\BackupListData`,
  `Data\BackupDetailData`, `Data\BackupActivityData`, `Data\BackupFilterData`.
- What is NEVER exposed: the storage path, the encryption password, the raw
  activity payload, Eloquent models.

## The storage lifecycle (what "delete" means here)

```
backup:run --only-db  ->  <backupName>/<filename>.zip        (active)
panel delete          ->  .backups-quarantine/<backupName>/  (reversible)
backups:purge-quarantine -> permanently deleted after 30 days
backup:clean          ->  spatie retention, never the newest
```

The panel's delete is a **move**, not a destructive call. That is deliberate:
an authenticated web request must not be able to destroy the only recoverable
dump, and it is the reason bulk delete + bulk restore can ship as a pair
(architecture §11). Irreversible deletion is a console command with a
`--dry-run`, a retention window and an audit entry.

**Database restore is not implemented.** Restoring a dump means dropping and
recreating the live schema — it needs maintenance mode, a verified archive and
an out-of-band runbook. Exposing that as a button would be the most dangerous
endpoint in the application. The archive is downloadable; the restore procedure
is an operator runbook, not HTTP. This is accepted debt, listed below.

## Applicable invariants (see skill §3)

1. `Domain/` imports no framework, ORM, HTTP or infrastructure — only native
   PHP. Verified by `ModuleComplexityTest`.
2. `Application/` imports domain, its own ports and `Data/`; no Eloquent, no
   facades, no `Illuminate\Http`. The filter object lives in `Data/` because it
   names `Request`; Application receives the validated value object.
3. Controllers validate, invoke one use case and map the response. Domain
   refusals are mapped to 422 in `BackupController::assertDomainOutcome`.
4. `BackupArchiveStore` is the interface; `SpatieBackupArchiveStore` lives in
   `Infrastructure/Persistence/` and has a different name.
5. Dependencies point inward. Infrastructure is the only layer that names
   spatie, `Storage`, `Cache`, `Artisan` and `Ramsey\Uuid`.
6. No other module is touched. The audit and export ports come from `Shared`.
7. No orphan class: every DTO, action and port has a caller today.
8. No dumb pass-through: the adapter resolves disks, paths, UUIDs, zip
   filtering, cache invalidation and move semantics; it is not a forwarder.

## This is an OCP bet — name it and date it

- Bet made on: 2026-10-28
- Review due: 2027-04-28 — two quarters from the bet date
- What would prove this was over-engineering: if by 2027-04-28 the guard has
  never refused a deletion in production **and** no test outside
  `Backups/Tests` substitutes `BackupArchiveStore`, the ports are ceremony and
  the module should be demoted to FLAT.

## Accepted debt and de-escalation condition

1. **No database restore endpoint** — see above. Trigger to revisit: a tested
   restore runbook with maintenance mode and archive verification.
2. **Inventory is cached for 30 seconds** in the adapter. A dump created by the
   cron in another process can take up to 30s to appear. Accepted: the
   alternative is an S3 `allFiles` on every poll. Invalidation is immediate for
   mutations made through the panel.
3. **`uuid5` is deterministic** from `state|disk|path`, so it is guessable to
   someone who knows the filename format. It is an identifier, not an
   authorization boundary: every route requires a permission, and every archive
   is global (no owner to leak). `whereUuid` + re-resolution against the
   inventory is the control.
4. **In-memory filtering/pagination** instead of SQL. Retention keeps tens of
   archives, so this is cheaper than a ledger; if the inventory ever exceeds
   ~1,000 files, that is the trigger for a real table.
5. **JSON-only HTTP surface.** The routes answer the admin client; the Inertia
   screen is a separate `frontend-new` task. The `Data/` classes are already in
   `resources/js/generated/generated.d.ts`, so the screen consumes generated
   types rather than hand-written ones.
6. **Single destination.** The package supports several disks; this config has
   one. 3-2-1-1-0 (three copies, two media, one offsite, one immutable, zero
   unverified restores) needs a second destination with Object Lock/versioning
   and restore drills. Quarantine is a reversible delete, not immutability.

Demote to FLAT when 0/4 triggers hold for two quarters and no test-covered
invariant remains in `Domain/`. Demoting means moving the files back to flat —
not deleting logic.

## Tests holding the layers up (no-lock rule)

- `Tests/Unit/BackupLifecycleGuardTest.php` — pure domain: newest refused,
  older allowed, restore conflict refused.
- `Tests/Unit/DeleteBackupsActionTest.php` — DB-free handler with a mocked
  `BackupArchiveStore` + `AuditPort`.
- `Tests/Unit/RunDatabaseBackupJobTest.php` — job with a mocked runner.
- `Tests/Feature/BackupEndpointTest.php` — 401/403/422 + index, search,
  pagination, delete/restore/bulk pair, download, export, run, activity.
- `Tests/Feature/BackupQuarantineCommandTest.php` — expiry window, dry run.
- `Tests/Feature/SpatieBackupArchiveStoreTest.php` — real adapter on a fake
  disk: list, quarantine, restore, purge.
