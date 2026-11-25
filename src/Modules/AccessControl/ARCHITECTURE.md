# AccessControl — ARCHITECTURE.md (FLAT)

> Copy of `stubs/architecture/ARCHITECTURE.FLAT.md`, filled in.
> Full rules → `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`.
> `tests/Architecture/ModuleComplexityTest.php` parses the `mode:` line.

mode: FLAT

## Why FLAT

`Role` and `Permission` are Spatie's models, not this module's aggregates. The module owns a
**catalogue surface** over vendor tables: list/search/export, create, rename, permission sync,
delete guards. `SKILL-ARCHITECTURE.md` §4 lists `laravel-permission` as **never PROGRESSIVE**; there
is no second model with business rules, no persistence that needs substitution, and no roadmap to
extract.

One module holds both resources because a role edit screen is where permissions are assigned
(`syncPermissions`). Splitting Roles and Permissions would make Roles import Permissions' queries or
duplicate them, and the filters/exports/audit/cache policy would exist twice.

## Triggers evaluated (1 of 4 — FLAT)

- [ ] **≥3 models with business rules** — zero owned models; `Role`/`Permission` are Spatie.
- [x] **Invariants** — system rows, assigned-row deletion, system-role names, active SUPER_ADMIN.
      They fit a Gate + three Actions, not a `Domain/` layer.
- [ ] **Tests that substitute persistence** — the Spatie tables _are_ the behaviour; a repository
      port would assert a mock.
- [ ] **Roadmap: extraction** — installation-level admin coupled to `App\Models\User` and the
      web guard.

## Boundaries and public contracts

- **Gate:** `manage-access` in `AccessControlServiceProvider` — active `SUPER_ADMIN` only. The route
  group uses `can:manage-access`; fine-grained `permission:*` middleware is defence in depth.
- **Shared vocabulary:** `Shared\Domain\Enums\Permission` carries the ten names used by routes and
  FormRequests. `Support\PermissionName` owns the identifier regex, uppercase normalisation and the
  module-group derivation.
- **System/custom boundary:** `permissions.is_system` (migration + seeder backfill) marks the seeded
  catalogue. `RoleData::systemNames()` derives system roles from `Shared\Domain\Enums\Role`.
- **Response shapes:** Spatie Data only — `RoleData`, `RoleDetailData`, `PermissionData`,
  `PermissionOptionData`, `PermissionCatalogData`, `FilterOptionsData`. No `JsonResource`.
- **Read contract:** index and export share `RoleListQuery` / `PermissionListQuery`, so a filter
  cannot exist on screen and be missing from a file.
- **Validation:** `Shared\Application\Rules\ExistingPermissionNames` resolves the whole permission array in one
  `whereIn`; the `max:1000` cap stays as the resource bound (no per-item `exists`), and each item carries a
  `max:64` bound matching the name contract.
- **Exports:** `ExportPort` + `{Role,Permission}ExportTransformer` + Blade views under
  `resources/views/exports/`. `ExportLimit` caps CSV/XLSX at 10,000 and PDF at 2,000; audited before
  streaming.
- **Audit:** `AuditPort`, `log_name = access-control`. Events: `role.created`, `role.updated`,
  `role.deleted`, `role.exported`, `permission.created`, `permission.updated`, `permission.deleted`,
  `permission.exported`, `access.cache_reset`. No `LogsActivity` on vendor models: explicit events
  match the Users module and never log a permission matrix or PII.
- **Cache:** Spatie owns the registry. Its model events and `syncPermissions()` already flush; each write
  additionally registers `DB::afterCommit()` so a concurrent read cannot repopulate the cache from the
  pre-commit snapshot and keep it for the 24 h TTL. `POST /access-control/cache-reset` is the operator path
  for out-of-band changes. No second cache.
- **Transactions:** every write (role create/update/delete, permission create/update/delete) wraps
  the mutation, the sync and the audit row in `DB::transaction`. Update/delete re-read with
  `lockForUpdate()` so two admins cannot race a guard.

## Invariants

1. Controllers validate transport input, invoke one Action or one Query, and map to Data.
2. No `Domain/`, `Application/`, `Infrastructure/` folders (the architecture test fails them).
3. System roles (`SUPER_ADMIN`, `ADMIN`) cannot be renamed or deleted; their permission sets remain
   editable.
4. A role assigned to any user cannot be deleted (422 with a count).
5. System permissions (`is_system = true`) cannot be renamed or deleted.
6. A permission assigned to any role or user cannot be deleted (422 with counts).
7. A custom row can be renamed while assigned, because grants are by id.
8. Names are uppercase identifiers (`^[A-Z][A-Z0-9_]{2,63}$`), normalised by the FormRequest.
9. Every route is `auth` + `verified` + `can:manage-access` + `permission:*`; the export also requires
   the view permission.
10. Every list uses `withCount`; the edit screen loads permissions once; no per-row query.

## Routes

`GET/POST /roles`, `GET /roles/create`, `GET /roles/{role}/edit`, `PUT/DELETE /roles/{role}`,
`GET /roles/export/{format}`, `GET/POST /permissions`, `PUT/DELETE /permissions/{permission}`,
`GET /permissions/export/{format}`, `POST /access-control/cache-reset`.

`roles` and `permissions` carry a public `uuid` (UUIDv7) alongside the vendor numeric `id`.
`App\Models\{Role,Permission}` add `HasUuids` + `uniqueIds() => ['uuid']` +
`getRouteKeyName() => 'uuid'`, and `config/permission.php` points at them. The numeric key stays
internal so Spatie's pivot tables are untouched; routes use `->whereUuid()`.

## Accepted debt

- No soft delete/restore/bulk. The vendor tables have no soft-delete contract; adding one would
  change Spatie's authorization queries. The UI exposes no row selection, so the "bulk delete +
  bulk restore" rule is not triggered. `RESTORE_ROLE`/`BULK_RESTORE_ROLE` stay in the seeded matrix
  unused.
- The `group` filter compares the suffix with `substr(name, length(name) - N + 1)`, which is portable
  across PostgreSQL (the deployed engine), SQLite and MySQL. A negative start (`substr(name, -N)`) is
  **not** portable: PostgreSQL treats it as "from character 1" and silently returns no rows. A
  dedicated group column was rejected: the name is the catalogue's source of truth.
- Inertia pages are part of this module. The JSON list contract (`rows` + `total`,
  `filter[...]`, `sort`, `per_page`) remains available to table clients.
- Exports are synchronous and capped per format: CSV/XLSX stream up to 10,000 rows, PDF renders
  up to 2,000 (dompdf runs in-request). The transformer also applies `limit($cap)` as a race guard, so
  a row inserted between the count and the stream cannot exceed the ceiling. The audit row is written
  after the rows are produced, not before the stream starts. A queued export is the follow-up that
  removes the ceiling.
- `is_system` is an additive column on a vendor table. Custom Spatie models were rejected because
  the flag does not change authorization semantics, only the admin write guards.
- `UPDATE_ROLE` also replaces the role's permission matrix; there is no separate
  `ASSIGN_PERMISSIONS_ROLE`. That is safe while `can:manage-access` admits only an active
  SUPER_ADMIN. If the Gate ever widens, split the capability before widening it.
- **Provisioning is first-install only.** `RolePermissionSeeder` creates the catalogue and grants the
  initial matrix on a fresh database; re-seeding an existing installation does not restore a permission
  an operator deliberately removed. `UserSeeder` is `local`/`testing` only, requires
  `SEED_USER_PASSWORD`, and never resets an existing account's password or roles. Production admins are
  provisioned explicitly with a secret-managed credential.
- The JSON list contract (`rows` + `total`, `filter[...]`, `sort`, `per_page`) is internal to the table
  client. Scramble documents only `api/*`, so these web endpoints are deliberately **not** part of the
  OpenAPI surface; session-authenticated endpoints must not be advertised as public API.

## Tests holding the module down

- `Tests/Feature/RoleCrudTest.php` — 401/403, index/filters/sort, case-insensitive search, create/sync/audit,
  duplicate, update, system-role name guard (HTTP and action boundary), delete guards (including a
  soft-deleted assignee), cache reset.
- `Tests/Feature/PermissionCrudTest.php` — index/group/role filters, case-insensitive search, literal
  wildcard search, custom create/rename/delete, system read-only, assigned delete guard (including a
  soft-deleted assignee), cache.
- `Tests/Feature/AccessControlExportTest.php` — CSV/XLSX/PDF, same filters, permission pairs, audit,
  per-format cap rejection.
- `Tests/Feature/AccessControlGateTest.php` — guest, delegated user, inactive super admin, stripped
  super admin, and a stripped-permission 403 for every mutating route and the cache reset.
- `Tests/Feature/AccessControlPagesTest.php` — Inertia components and payloads.
- `Tests/Unit/RoleFilterDataTest.php`, `PermissionFilterDataTest.php`, `PermissionNameTest.php`,
  `ExportLimitTest.php`, `SearchPatternTest.php`.

## Mode review

- Declared FLAT on: 2026-11-24.
- Review due: 2027-05-24 (two quarters).
- What would promote it: a second consumer that needs the catalogue without HTTP (e.g. a provisioning
  service), or a permission lifecycle state machine. Neither exists today.
- What would demote it: nothing — it is already FLAT.
