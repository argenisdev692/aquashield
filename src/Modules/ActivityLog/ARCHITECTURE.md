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
  `GET /activity/export/{csv,xlsx,pdf}`.
- REST routes (Sanctum, read-only, Scramble-documented): `GET /api/activity`,
  `GET /api/activity/{uuid}`.
- Permissions: `VIEW_ANY_ACTIVITY_LOGS`, `VIEW_ACTIVITY_LOGS`,
  `EXPORT_ACTIVITY_LOGS` (seeded in `RolePermissionSeeder::READ_ONLY_MODULES`).
- Models: `Models\ActivityEntry` extends Spatie's `Activity` and adds the `uuid`
  the panel exposes; the auto-increment id never crosses HTTP.
- Response shapes: `Data\ActivityEntryData`, `Data\ActivityListData`,
  `Data\ActivityDetailData`, `Data\ActivityFilterData`.
- What is NEVER exposed: the auto-increment `id`, `attribute_changes`, raw
  `properties`, causer email, subject id.

## Deliberate omissions

- **No delete or restore.** An audit trail a panel can rewrite is not an audit
  trail (OWASP A09). Retention belongs to `activitylog:clean`, run monthly by
  the scheduler with a 7-year default.
- **Export is a read, not a mutation.** It reuses the list filters and the
  `EXPORT_ACTIVITY_LOGS` permission; the email properties are not exported.
- **The detail view shows the allowlisted properties, not the full diff.**
  `attribute_changes` can contain PII; the database keeps it, the screen does
  not. Revisit only with a field-level allowlist per subject type.
- **No FormRequest.** The read routes validate through the filter value object;
  the export path uses the same object, so the CSV cannot escape the filters.

## Tests holding the module up

- `Tests/Feature/ActivityLogEndpointTest.php` — 401 / 403 / 422, list filters,
  pagination, UUID show, 404, property allowlist.
