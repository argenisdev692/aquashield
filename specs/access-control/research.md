# Access Control — Research

- **Date:** 2026-11-24
- **Method:** installed versions from `composer show`, release state from Firecrawl (Packagist/GitHub),
  API behaviour from Context7 scoped to the installed major, plus the vendor source in
  `vendor/spatie/laravel-permission/src/Models`.

## 1. Installed stack (authoritative)

| Package                    | Installed | Latest seen | Source / decision                                                                                                               |
| -------------------------- | --------- | ----------- | ------------------------------------------------------------------------------------------------------------------------------- |
| PHP                        | 8.5.13    | 8.5.x       | `php artisan about`; rules.md stack table 2026-11-23.                                                                           |
| laravel/framework          | 13.43.0   | 13.43.0     | `composer outdated --direct` does not list it → current.                                                                        |
| spatie/laravel-permission  | 8.3.2     | **8.5.0**   | [Packagist](https://packagist.org/packages/spatie/laravel-permission) (8.5.0, 2026-11-06). Upgrade in A10; no breaking changes. |
| spatie/laravel-data        | 4.23.0    | 4.23.1      | `composer show`; patch only, not required by this feature.                                                                      |
| spatie/laravel-activitylog | 5.1.3     | 5.1.5       | `composer show`; the `AuditPort` adapter already wraps it.                                                                      |
| barryvdh/laravel-dompdf    | 3.1.2     | 3.1.2       | `composer show`; `ExportPort` owns the renderer.                                                                                |
| pestphp/pest               | 5.3.4     | 5.3.7       | `composer outdated`; test runner for this module.                                                                               |
| larastan/larastan          | 3.13.1    | 3.15.0      | `composer outdated`; PHPStan L5 is in the closing gate.                                                                         |
| inertiajs/inertia-laravel  | 3.4.0     | 3.8.0       | `composer outdated`. Not upgraded: unrelated to this module, and the client/server pair is working.                             |
| @inertiajs/react           | 3.11.0    | —           | `package.json`.                                                                                                                 |
| @tanstack/react-table      | 9.3.1     | —           | `package.json`; `DataTableKit` already owns v9 (`useTable` + `features`).                                                       |
| react / react-dom          | 19.3.1    | —           | `package.json`; function components + hooks only.                                                                               |
| typescript                 | 7.0.2     | —           | `package.json`; gate is `tsc --noEmit`, no compiler API.                                                                        |

## 2. Spatie API facts that shape the implementation

Verified in the vendor source and Context7:

- `Role::create([...])` throws `Spatie\Permission\Exceptions\RoleAlreadyExists` on duplicate; a
  `Role::findOrCreate()` exists for idempotent seeding.
- `Permission::create([...])` throws `PermissionAlreadyExists`; `Permission::findOrCreate()` is
  idempotent.
- `Role::permissions()` is a `belongsToMany` (`role_has_permissions`); `Role::users()` is a
  `morphedByMany` over `model_has_roles`.
- `Permission::roles()` is a `belongsToMany`; `Permission::users()` is a `morphedByMany` over
  `model_has_permissions`.
- `syncPermissions()` **replaces** the set (not additive); the package flushes its cache. Source:
  [Spatie role/permissions docs](https://github.com/spatie/laravel-permission/blob/main/docs/basic-usage/role-permissions.md).
- Cache: the `Role` and `Permission` models use `RefreshesPermissionCache`; creating/deleting and
  `syncPermissions()` reset the cache automatically. Direct DB writes do **not** — use
  `PermissionRegistrar::forgetCachedPermissions()`. Source:
  [Spatie cache docs](https://github.com/spatie/laravel-permission/blob/main/docs/advanced-usage/cache.md).
- v8 keeps `forgetCachedPermissions()`; the v7→v8 upgrade notes still recommend it after seeding.
  Source: [Upgrading](https://spatie.be/docs/laravel-permission/v8/upgrading).
- At research time the package tables had **no** `uuid`, `deleted_at`, `is_system` or group column.
  **Superseded 2026-11-24:** additive columns are `permissions.is_system` and `roles.uuid` /
  `permissions.uuid` (public UUIDv7); the group is derived from the name.

## 3. Release state (Firecrawl, 2026-11-24)

- 8.5.0 (2026-11-06): adds `canAny()`/`canAll()` route macros and authorization coverage. **No
  breaking changes**, PHP/Laravel requirements unchanged. Source:
  [GitHub release 8.5.0](https://github.com/spatie/laravel-permission/releases/tag/8.5.0).
- 8.4.1: fixes `teams()` queries when teams are disabled (our config). No breaking changes.
- 8.4.0: route-group macros; no breaking changes.
- 8.3.2 (installed): cache lookup performance; no API change.

`composer outdated --direct` also reports 6 advisories against `web-auth/webauthn-lib` 5.3.9
(fixed in 5.3.13). That is a security update outside this feature's code but inside its gate: it is
applied so `composer audit` (and therefore `laravel/doctor`) can pass.

## 4. Laravel 13 facts

- `DB::transaction()` commits on success and rolls back on an exception; the closure's return value is
  returned. Source: [Laravel 13 database docs](https://github.com/laravel/docs/blob/13.x/database.md).
- `withCount()` adds `{relation}_count` without loading the relation, and accepts an array for multiple
  relations. Source: [Laravel 13 relationships docs](https://github.com/laravel/docs/blob/13.x/eloquent-relationships.md).
- Date filters use a range predicate on the bare `created_at` column (index-friendly), not
  `whereDate()`. The repository already standardised this in `UserListQuery` / `ActivityListQuery`.
- Routing: numeric route params use `->whereNumber()`; UUIDs use `->whereUuid()`. **Superseded
  2026-11-24:** the Spatie rows keep the integer key internally but expose `uuid`, so the access
  routes use `->whereUuid()`.

## 5. Repository conventions confirmed on disk

- Closest FLAT reference: `src/Modules/Users` — `Actions`, `Data`, `Http/Controllers`,
  `Http/Requests`, `Queries`, `Exports`, `Providers`, `Routes`, `Tests`.
- Exports: module ships a transformer + Blade view; `Shared\Domain\Ports\ExportPort` owns streaming
  and PDF.
- Audit: `Shared\Domain\Ports\AuditPort` explicit events. The Users module deliberately does not put
  `LogsActivity` on `User`; this module follows the same explicit-event pattern with
  `log_name = access-control`.
- Frontend: `DataTableKit` + `useCrudTable`; `CrudFilters`; `CrudDialog`; `useZodForm`; generated
  types from Spatie Data via `php artisan typescript:transform`.
- `tests/Architecture/ModuleComplexityTest.php` parses `mode: FLAT` and fails `Domain/`,
  `Application/`, `Infrastructure/` directories in a FLAT module.

## 6. Decisions that research changed

1. **No soft deletes/bulk restore.** The seeded matrix includes `RESTORE_ROLE`/`BULK_RESTORE_ROLE`, but
   adding soft deletes would change Spatie's global queries and authorization semantics. The module
   consequently exposes no row selection, so the repo's "bulk delete + bulk restore" rule is not
   triggered. Single delete with hard guards is the smaller, safer contract.
2. **`is_system` instead of inferring from the seeder.** The enum only names the Users permissions; the
   seeder's 514-name matrix is private. A boolean column is explicit, backfillable and testable.
3. **System-role names fixed, permission sets editable.** `SUPER_ADMIN`/`ADMIN` are code constants, but
   an operator must be able to repair the `SUPER_ADMIN` permission set. The name is the only immutable
   part.
4. **Explicit cache flush after every write.** The package already flushes, but the action calls
   `forgetCachedPermissions()` at the end of the transaction so the contract is local, testable and
   survives a future move away from the default models.

## 7. Open questions

None. Every `[NEEDS CLARIFICATION]` was resolved as an assumption in `spec.md` §Assumptions (A1–A10).
