# ActivityLog — ARCHITECTURE.md

mode: FLAT

## Why FLAT

One vendor-owned aggregate (`Spatie\Activitylog\Models\Activity`, extended only
to add a UUID), read-only screens, no state machine and no rule a permission
string cannot express. **0/4 triggers**:

- [ ] ≥3 models with business rules — one vendor model.
- [ ] Invariants — no mutation at all; the audit write belongs to the producer.
- [ ] Tests substitute persistence/external — no external service.
- [ ] Extraction roadmap — no.

`spatie/laravel-activitylog` owns this domain; the architecture skill's table
says it is never a DDD candidate. The panel is a read-only projection.

## Boundaries and public contracts

- Web routes: `GET /activity`, `GET /activity/{uuid}` (Inertia + JSON),
  `GET /activity/export/{format}` (`csv`, `xlsx`, `pdf`).
- Permissions: `VIEW_ANY_ACTIVITY_LOGS`, `VIEW_ACTIVITY_LOGS`,
  `EXPORT_ACTIVITY_LOGS` (seeded in `RolePermissionSeeder::READ_ONLY_MODULES`).
- Models: `Models\ActivityEntry` extends Spatie's `Activity` and adds the `uuid`
  the panel exposes; the auto-increment id never crosses HTTP.
- Response shapes: `Data\ActivityEntryData` (one row, list and detail) and
  `Data\ActivityFilterData` (the list/export query contract).
- What is NEVER exposed: the auto-increment `id`, `attribute_changes`, raw
  `properties`, causer email, subject id.
- `properties` is an **event-keyed projection**, not a global key list: each
  known event declares the fields it may expose (role/user/export/backup/auth).
  Unknown events expose nothing; `email`, `ip_address`, `user_agent`, `filters`,
  `requested_by` and every secret are absent from every arm.
- Timestamps leave the module as ISO 8601 with an offset
  (`toIso8601String()`); the browser converts them to the viewer's timezone.
- Causers are eager-loaded with the soft-delete global scope removed: a deleted
  actor still resolves to a name instead of “System”.
- `page` is capped at 10 000; `per_page` at 100.
- The read/export model selects explicit columns; `attribute_changes` and
  `subject_id` are never hydrated.
- The projection is defined **once** in `Shared\Support\ActivityProperties`; this
  module and the Backups activity reader consume the same event-keyed map.

## Invariants enforced by the module

- `Models\ActivityEntry` refuses `updating` and `deleting`: the panel is
  append-only. Retention goes through `activitylog:clean`, whose query delete
  does not fire model events.
- `Providers\ActivityLogServiceProvider::assertValidConfiguration()` fails the
  boot when `activitylog.clean_after_days < 365` or when
  `ACTIVITYLOG_BUFFER_ENABLED` is on. Buffered inserts bypass `HasUuids` and the
  `uuid` column is `NOT NULL`; a zero retention window makes the monthly clean
  delete the entire trail.
- Retention uses `Actions\ChunkedCleanActivityLogAction` (1 000 rows per batch)
  through `activitylog.actions.clean_log`, so a seven-year clean never issues one
  multi-million-row `DELETE`.
- `ACTIVITYLOG_AUDIT_VIEWS` (default `false`) opts into `activity.viewed` rows on
  the detail screen. List views are never audited; the flag exists only for a
  compliance requirement that asks who viewed a specific entry.

## Deliberate omissions

- **No REST surface in this phase.** The panel is web-only; no `/api/activity`
  route is registered. Add a Sanctum, read-only API (with Scramble docs) only
  when a second consumer exists — the same Data classes can serve it.
- **No delete or restore.** An audit trail a panel can rewrite is not an audit
  trail (OWASP A09). Retention belongs to `activitylog:clean`, run monthly by
  the scheduler with a 7-year default.
- **Export is a read, not a mutation.** It reuses the list filters and the
  `EXPORT_ACTIVITY_LOGS` permission; the email properties are not exported.
- **The detail view shows the event-keyed projection, not the full diff.**
  `attribute_changes` can contain PII; the database keeps it, the screen does
  not. A new producer event must add its own safe keys to
  `Shared\Support\ActivityProperties` and a test; unknown events expose nothing.
- **No FormRequest.** The read routes validate through the filter value object;
  the export path uses the same object, so the CSV cannot escape the filters.

## Declared cross-module exception (2026-11-26)

`Backups\Infrastructure\Persistence\SpatieBackupActivityLog` imports
`Modules\ActivityLog\Models\ActivityEntry` because `activity_log` is a shared,
vendor-owned audit substrate and the read is confined to one infrastructure
adapter. This is a declared exception to the “ports, DTOs or public Data only”
matrix, not a fourth option. Review due **2027-05-19**; extract a read port/Data
if a third consumer appears.

## Performance budget (measured 2026-11-26)

`EXPLAIN (ANALYZE, BUFFERS)` against the dev database (86 rows) recorded the
baseline for the three real predicates:

- the list page uses `activity_log_created_at_index` (Backward Index Scan +
  Incremental Sort);
- the free-text search is a `Seq Scan`: a leading-wildcard `LIKE` cannot use a
  B-tree index;
- the export `count()` shares the search predicate, so it scans the same rows.

**No trigram index is added yet.** At this volume the planner is right to
prefer the sequential scan, and a GIN index would tax every audit insert —
`activity_log` is write-heavy by definition. Re-measure when the table passes
~500 000 rows or when the p95 of a filtered list exceeds 500 ms; then install
`pg_trgm` and add GIN indexes on `event`, `description` and `log_name` in a
dedicated migration, after confirming the plan. The date window is already
index-friendly: `ActivityListQuery` uses `>=` / `<` on the bare `created_at`
column, not `whereDate()`.

## Tests holding the module up

- `Tests/Feature/ActivityLogEndpointTest.php` — 401 / 403 / 422, list filters,
  pagination, UUID show, 404, event-keyed property projection, ISO timestamps,
  soft-deleted causer, export audit.
- `Tests/Feature/ActivityLogSchemaTest.php` — `uuid` is required and unique at
  the schema level, and the model refuses update/delete.
- `Tests/Feature/ActivityLogCleanTest.php` — the chunked retention action.
- `Tests/Unit/ActivityLogConfigTest.php` — the retention and buffer guards.
- `Tests/Unit/ActivityFilterDataTest.php` — the filter contract, including the
  page cap and the inverted date window.
- `tests/Unit/ActivityPropertiesTest.php` — the one shared projection map
  (PII exclusion, unknown events, backup keys).
