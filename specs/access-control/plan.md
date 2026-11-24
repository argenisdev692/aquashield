# Access Control — Implementation Plan

- **Spec:** `specs/access-control/spec.md`
- **Research:** `specs/access-control/research.md`
- **Mode:** FLAT
- **Date:** 2026-11-24

## 1. Mode decision — FLAT

Triggers evaluated (0/4 → FLAT):

| Trigger                                    | Verdict                                                                                                                                                                    |
| ------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ≥3 owned models with business rules        | **No.** `Role`/`Permission` are Spatie models; the module owns no aggregate.                                                                                               |
| Invariants                                 | **Yes**, but they fit a gate + actions (system rows, assigned rows, name immutability). `laravel-permission` is listed as **never PROGRESSIVE** in the architecture skill. |
| Tests must substitute persistence/external | **No.** The permission tables _are_ the behaviour; a repository port would assert a mock.                                                                                  |
| Roadmap: extraction to a microservice      | **No.** It is installation-level admin, coupled to the app's auth model.                                                                                                   |

No `Domain/`, `Application/`, `Infrastructure/`. The rules live in `Support/` + `Actions/` + a Gate,
which is the declared Users-module pattern.

## 2. Module tree on disk

```
src/Modules/AccessControl/
├── ARCHITECTURE.md
├── Actions/
│   ├── CreateRoleAction.php
│   ├── UpdateRoleAction.php
│   ├── DeleteRoleAction.php
│   ├── CreatePermissionAction.php
│   ├── UpdatePermissionAction.php
│   ├── DeletePermissionAction.php
│   └── ResetPermissionCacheAction.php
├── Data/
│   ├── RoleData.php
│   ├── RoleDetailData.php
│   ├── RoleFilterData.php
│   ├── RoleInputData.php
│   ├── PermissionData.php
│   ├── PermissionOptionData.php
│   ├── PermissionCatalogData.php
│   ├── PermissionFilterData.php
│   ├── PermissionInputData.php
│   └── FilterOptionsData.php
├── Exports/
│   ├── RoleExportTransformer.php
│   └── PermissionExportTransformer.php
├── Http/
│   ├── Controllers/
│   │   ├── RoleController.php
│   │   ├── RoleExportController.php
│   │   ├── PermissionController.php
│   │   ├── PermissionExportController.php
│   │   └── PermissionCacheController.php
│   └── Requests/
│       ├── StoreRoleRequest.php
│       ├── UpdateRoleRequest.php
│       ├── StorePermissionRequest.php
│       └── UpdatePermissionRequest.php
├── Providers/AccessControlServiceProvider.php
├── Queries/
│   ├── RoleListQuery.php
│   ├── PermissionListQuery.php
│   ├── PermissionCatalogQuery.php
│   └── FilterOptionsQuery.php
├── Routes/web.php
├── Support/PermissionName.php
├── Tests/
│   ├── Feature/
│   │   ├── RoleCrudTest.php
│   │   ├── PermissionCrudTest.php
│   │   ├── AccessControlExportTest.php
│   │   ├── AccessControlGateTest.php
│   │   └── AccessControlPagesTest.php
│   └── Unit/
│       ├── RoleFilterDataTest.php
│       ├── PermissionFilterDataTest.php
│       └── PermissionNameTest.php
│   └── Support/AccessControlFixtures.php
```

Frontend:

```
resources/js/Modules/AccessControl/schemas/
├── role.ts
└── permission.ts
resources/js/Pages/AccessControl/
├── Roles/
│   ├── Index.tsx
│   ├── Create.tsx
│   ├── Edit.tsx
│   └── components/
│       ├── RoleForm.tsx
│       └── PermissionMatrix.tsx
└── Permissions/
    ├── Index.tsx
    └── components/PermissionDialog.tsx
```

Shared changes: `bootstrap/providers.php`, `app/Http/Middleware/HandleInertiaRequests.php`,
`resources/js/lib/navigation.ts`, `AppLayout.tsx`, `AppSidebar.tsx`,
`Shared\Domain\Enums\Permission`, `RolePermissionSeeder`, `Component` set, new migration, new Blade
exports.

## 3. Data model

No new Eloquent model. Spatie's `Spatie\Permission\Models\Role` / `Permission` stay the aggregate.

One additive migration: `database/migrations/2026_11_24_000004_add_is_system_to_permissions_table.php`

| Column      | Type               | Rule                                                         |
| ----------- | ------------------ | ------------------------------------------------------------ |
| `is_system` | boolean, default 0 | `1` = seeded/code-referenced catalogue; `0` = module-created |

- Backfill: every existing row becomes `1` (all 514 came from the seeder).
- `RolePermissionSeeder::ensurePermissions()` uses `updateOrCreate(..., ['is_system' => true])`, so a
  re-seed re-protects a catalogue name that a human may have created earlier as custom.
- Roles are system when `name ∈ {SUPER_ADMIN, ADMIN}` (`Shared\Domain\Enums\Role`). Derived, no column.
- No `deleted_at`, no group column. **Superseded 2026-11-24:** a public `uuid` (UUIDv7) was added to
  `roles`/`permissions`; the vendor numeric `id` stays internal. See `ARCHITECTURE.md`.

## 4. HTTP surface

All routes: `web`, `auth`, `verified`, `can:manage-access`, `throttle:60,1`.

| Method | URI                            | Name                         | Permission                                  | Controller                     |
| ------ | ------------------------------ | ---------------------------- | ------------------------------------------- | ------------------------------ |
| GET    | `/roles`                       | `roles.index`                | `VIEW_ANY_ROLE`                             | `RoleController@index`         |
| GET    | `/roles/create`                | `roles.create`               | `CREATE_ROLE`                               | `RoleController@create`        |
| POST   | `/roles`                       | `roles.store`                | `CREATE_ROLE`                               | `RoleController@store`         |
| GET    | `/roles/export/{format}`       | `roles.export`               | `VIEW_ANY_ROLE` + `EXPORT_ROLE`             | `RoleExportController`         |
| GET    | `/roles/{role}/edit`           | `roles.edit`                 | `UPDATE_ROLE`                               | `RoleController@edit`          |
| PUT    | `/roles/{role}`                | `roles.update`               | `UPDATE_ROLE`                               | `RoleController@update`        |
| DELETE | `/roles/{role}`                | `roles.destroy`              | `DELETE_ROLE`                               | `RoleController@destroy`       |
| GET    | `/permissions`                 | `permissions.index`          | `VIEW_ANY_PERMISSION`                       | `PermissionController@index`   |
| POST   | `/permissions`                 | `permissions.store`          | `CREATE_PERMISSION`                         | `PermissionController@store`   |
| GET    | `/permissions/export/{format}` | `permissions.export`         | `VIEW_ANY_PERMISSION` + `EXPORT_PERMISSION` | `PermissionExportController`   |
| PUT    | `/permissions/{permission}`    | `permissions.update`         | `UPDATE_PERMISSION`                         | `PermissionController@update`  |
| DELETE | `/permissions/{permission}`    | `permissions.destroy`        | `DELETE_PERMISSION`                         | `PermissionController@destroy` |
| POST   | `/access-control/cache-reset`  | `access-control.cache-reset` | `UPDATE_PERMISSION`                         | `PermissionCacheController`    |

**Superseded 2026-11-24:** routes use `->whereUuid()`; `{role}` / `{permission}` bind the custom
`App\Models\{Role,Permission}` models by `uuid`. The vendor numeric `id` stays internal.

`manage-access` Gate (provider): `$user->isActive() && $user->hasRole(SUPER_ADMIN)`.

Rate limiter `access-control-exports`: 10/min per actor, keyed by uuid (named limiter, not bare
`throttle:10,1`).

## 5. Invariants (enforced where)

| #   | Rule                                                             | Enforcement                                                                 |
| --- | ---------------------------------------------------------------- | --------------------------------------------------------------------------- |
| I1  | Only an active `SUPER_ADMIN` reaches any route                   | `can:manage-access` route group + Gate; `AccessControlGateTest`             |
| I2  | Fine-grained permission still required                           | `permission:*` per route; 403 test with a stripped super admin              |
| I3  | System role names cannot change; they can still sync permissions | `UpdateRoleAction` + `UpdateRoleRequest`; feature test                      |
| I4  | A role with ≥1 user cannot be deleted                            | `DeleteRoleAction` with `lockForUpdate()->withCount('users')`; feature test |
| I5  | `SUPER_ADMIN` / `ADMIN` cannot be deleted                        | `DeleteRoleAction`; feature test                                            |
| I6  | System permissions cannot be renamed/deleted                     | `UpdatePermissionAction` / `DeletePermissionAction` on `is_system`; tests   |
| I7  | A permission assigned to a role or user cannot be deleted        | delete action counts both relations; test                                   |
| I8  | Name regex `^[A-Z][A-Z0-9_]{2,63}$`; requests trim+uppercase     | FormRequests + Zod schema; 422 tests                                        |
| I9  | Every write/export audits exactly one event                      | `AuditPort`, `log_name=access-control`; audit assertions                    |
| I10 | Every write flushes the permission registry                      | `ResetPermissionCacheAction` / `forgetCachedPermissions()` in actions       |
| I11 | Export filters == list filters                                   | both consume `*FilterData` + the same query builder; export test            |

## 6. Query / N+1 plan

- `RoleListQuery::builder()` — search, type, permission (`whereHas('permissions')`), date range,
  allowlisted `orderBy`. `paginate()` adds `withCount(['permissions', 'users'])`.
- `PermissionListQuery::builder()` — search, type, group suffix, role (`whereHas('roles')`), date range.
  `paginate()` adds `withCount(['roles', 'users'])`.
- `PermissionCatalogQuery` — one query for the matrix (`name`, `is_system`), grouped in PHP.
- `FilterOptionsQuery` — one `pluck('name')` for groups + one role-name query; no per-row query.
- Export uses `->lazy(500)` with counts already selected; never `cursor()` + lazy relations.
- Role edit loads `permissions:id,name` once; no per-permission query.

## 7. Transactions, cache, audit

- Create/update/delete actions wrap the write + sync + audit in `DB::transaction`.
- `lockForUpdate()` on update/delete reads so two admins cannot race the guards.
- `PermissionRegistrar::forgetCachedPermissions()` at the end of each write action.
- Audit events (explicit, no `LogsActivity`):
  `role.created`, `role.updated`, `role.deleted`, `role.exported`,
  `permission.created`, `permission.updated`, `permission.deleted`, `permission.exported`,
  `access.cache_reset`.
- Properties carry names/counts only — never a password, token or PII.

## 8. Exports

- `RoleExportTransformer`: name, type, permission count, user count, created, updated.
- `PermissionExportTransformer`: name, group, type, role count, user count, created, updated.
- `MAX_ROWS = 10_000`; controller refuses 422 before streaming.
- `resources/views/exports/roles.blade.php` and `permissions.blade.php` copied from the Users
  template (dompdf CSS subset).
- Both exports require the view permission as well as the export permission, and record an audit row
  with non-PII filter metadata.

## 9. Frontend plan

- `lib/navigation.ts`: add **Roles** (`ShieldCheck`, `/roles`) and **Permissions**
  (`LockKeyhole`, `/permissions`), both gated by capability `manageAccess`.
- `HandleInertiaRequests`: share `canManageAccess`; `AppLayout`/`AppSidebar` pass it through.
- `Roles/Index`: `DataTableKit` + `useCrudTable`; filters type/permission/date; actions Edit/Delete;
  header action New role + Flush cache; delete confirmation `CrudDialog`.
- `Roles/Create` & `Edit`: `useZodForm`, `TextField` name, `PermissionMatrix` (search, group sections,
  select all per group, selected count), `StickyFormBar`, unsaved-changes warning.
- `Permissions/Index`: `DataTableKit`; filters type/group/role/date; create/edit `PermissionDialog`
  with `TextField`; delete `CrudDialog`; `actionError` surfaces a 422 (assigned rows).
- Generated types only: `Modules.AccessControl.Data.*` and filter types via `typescript:transform`.
  No hand-written mirror interfaces.

## 10. Tests

- `RoleCrudTest`: 401/403/422 + index JSON/Inertia, filters/sort, create (sync + audit + cache),
  duplicate, update (custom rename, system rename refused, sync), delete (system/assigned refused,
  custom success).
- `PermissionCrudTest`: index filters (type/group/role/date), create custom, system rename/delete
  refused, assigned delete refused, custom rename/delete, unknown id 404.
- `AccessControlExportTest`: CSV/XLSX/PDF, same filters, 403 without export permission, 403 without
  view permission, audit row.
- `AccessControlGateTest`: guest, delegated user with every permission, inactive super admin,
  stripped super admin.
- `AccessControlPagesTest`: Inertia props/abilities, create/edit payloads, no permission leakage.
- Unit: `RoleFilterDataTest`, `PermissionFilterDataTest`, `PermissionNameTest` (group derivation,
  regex).

## 11. Verified versions in this plan

| Component                 | Version        | Verified from                                                                                                                                           |
| ------------------------- | -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| spatie/laravel-permission | 8.5.0 (target) | [Packagist](https://packagist.org/packages/spatie/laravel-permission), [8.5.0 release](https://github.com/spatie/laravel-permission/releases/tag/8.5.0) |
| Laravel                   | 13.43.0        | `php artisan about`, `composer outdated --direct` (not listed)                                                                                          |
| PHP                       | 8.5.13         | `php artisan about`                                                                                                                                     |
| spatie/laravel-data       | 4.23.0         | `composer show`                                                                                                                                         |
| activitylog               | 5.1.3          | `composer show`                                                                                                                                         |
| dompdf                    | 3.1.2          | `composer show`                                                                                                                                         |
| Pest                      | 5.3.4          | `composer show`                                                                                                                                         |
| React / TanStack Table    | 19.3.1 / 9.3.1 | `package.json`                                                                                                                                          |

## 12. Risks / mitigations

| Risk                                                      | Mitigation                                                                                                  |
| --------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Spatie upgrade changes behaviour                          | 8.5.0 notes say no breaking changes; run the full suite; the module uses only stable public methods.        |
| `withCount('users')` on `morphedByMany` with one guard    | Only the `web` guard exists; `Role::users()` resolves `App\Models\User`. Test the aggregate on SQLite.      |
| 514-checkbox matrix is heavy                              | Search + group sections + select-all; React Compiler memoises; no per-checkbox network call.                |
| Deleting a permission silently breaks a route             | `is_system` protects the seeded catalogue; custom rows assigned to anything cannot be deleted.              |
| Cache stale after a rollback                              | Flush is idempotent; an empty cache repopulates from the DB, it cannot serve rolled-back data.              |
| Local `doctor` already fails on pending migration / audit | Apply pending migrations, upgrade `web-auth/webauthn-lib` to ≥5.3.13, re-run the gate; report any residual. |
