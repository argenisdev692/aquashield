# Access Control — Specification

- **Feature ID:** `access-control`
- **Date:** 2026-11-24
- **Mode:** FLAT (declared in `src/Modules/AccessControl/ARCHITECTURE.md`)
- **Owner:** Backend/Frontend module `AccessControl`

## Problem

`spatie/laravel-permission` is installed, configured (`config/permission.php`), migrated and seeded
(`database/seeders/RolePermissionSeeder.php`: 26 roles, 514 permissions). The **Users** module can
attach existing roles/direct permissions to a user, and `Support/AccessCeiling` stops privilege
escalation there. Nothing can _define_ a role, change its permission set, or retire a custom
permission without a deploy or tinker. The catalogue is frozen in a seeder.

## Decision: one module, two resources

`AccessControl` owns the authorization **catalogue** (roles + permission definitions). **Users** keeps
ownership of **grants to people** (`user.roles.assigned`, `user.permissions.assigned`).

Two modules were rejected:

- A role screen is where permissions are assigned (`syncPermissions`). Splitting would make `Roles`
  import `Permissions` queries/actions, which is the cross-module coupling the architecture rules
  forbid without a port.
- `Role` and `Permission` are one bounded context owned by Spatie. `SKILL-ARCHITECTURE.md` §4 lists
  `laravel-permission` as **never PROGRESSIVE**; it also gives no independent lifecycle to split on.
- The filters, exports, audit policy and cache invalidation would be duplicated.

## User stories and acceptance criteria

### US-01 — Browse roles

As a Super Admin I can list roles with search, type/permission/date filters, sorting and pagination.

**Acceptance criteria**

- `GET /roles` answers JSON (`rows`, `total`, `page`, `per_page`) to an XHR and an Inertia page to a
  browser visit.
- Search matches the role name; `filter[type]` accepts `system|custom`; `filter[permission]` restricts
  to roles holding that permission; `filter[date_from]`/`[date_to]` filter `created_at` inclusively.
- Sort accepts `name`, `created_at`, `updated_at`, `permissions_count`, `users_count`; anything else is
  a 422, never an arbitrary `orderBy`.
- Each row reports name, type, permission count, user count, created/updated timestamps.
- The query uses `withCount` for both aggregates (no per-row count query).

### US-02 — Create a role

As a Super Admin I can create a role with a name and an initial permission set.

**Acceptance criteria**

- `POST /roles` validates name (`required`, uppercase identifier, unique per `web` guard) and
  permissions (bounded array, each existing permission).
- Role, permission sync and audit row (`role.created`) commit in one transaction.
- The Spatie permission cache is flushed before the response.
- Duplicate names and unknown permissions return 422 with field errors, including the race between
  validation and insert.

### US-03 — Edit a role

As a Super Admin I can rename a custom role and replace its permission set.

**Acceptance criteria**

- `GET /roles/{role}/edit` returns the role plus the full permission catalogue for the matrix.
- `PUT /roles/{role}` syncs permissions (replace, not add) and audits `role.updated`.
- System roles `SUPER_ADMIN` and `ADMIN` keep their names; their permission sets remain editable.
- A rename to an existing name returns 422.
- The response is JSON for an XHR and a redirect for an Inertia form.

### US-04 — Delete a role

As a Super Admin I can delete a custom role that is not assigned to anyone.

**Acceptance criteria**

- System roles are refused with 422.
- A role assigned to one or more users is refused with 422 and a count in the message; deleting it
  would silently strip access.
- A successful delete removes the role, its permission links and its user links, audits
  `role.deleted`, and flushes the cache.
- An actor cannot delete a role they hold, because that role has at least one user.

### US-05 — Browse permissions

As a Super Admin I can list the permission catalogue with search, type/group/role/date filters and
sorting.

**Acceptance criteria**

- `GET /permissions` follows the same JSON/Inertia contract as US-01.
- Search matches the name; `filter[type]` accepts `system|custom`; `filter[group]` matches the module
  suffix (`VIEW_ANY_USER` → `USER`); `filter[role]` restricts to permissions held by that role;
  date filters apply to `created_at`.
- Sort accepts `name`, `created_at`, `updated_at`, `roles_count`, `users_count`.
- Each row reports name, group, type, role count, user count and timestamps.
- Counts come from `withCount`, never a relation load.

### US-06 — Create a custom permission

As a Super Admin I can add a permission that is not part of the seeded catalogue.

**Acceptance criteria**

- `POST /permissions` validates the name (uppercase identifier, unique per guard).
- The row is created with `is_system = false`; system rows can never be forged through the request.
- Creation is transactional and audited (`permission.created`), and flushes the cache.

### US-07 — Rename or delete a custom permission

As a Super Admin I can rename or delete a permission created through the module.

**Acceptance criteria**

- System permissions (seeded catalogue, `is_system = true`) are read-only: rename/delete return 422.
- A custom permission assigned to any role or user cannot be deleted (422 with the counts); renaming
  is allowed because assignments are by id.
- Successful writes audit `permission.updated` / `permission.deleted` and flush the cache.
- Unknown ids return 404.

### US-08 — Export the catalogue

As a Super Admin I can export the filtered role or permission list as CSV, XLSX or PDF.

**Acceptance criteria**

- `GET /roles/export/{format}` and `GET /permissions/export/{format}` accept `csv|xlsx|pdf`.
- The export consumes the **same** `*FilterData` and query builder as the list, so a filter cannot be
  applied on screen and dropped from the file.
- The route requires both the view permission and the matching export permission.
- Exports are capped per format — CSV/XLSX 10,000 rows, PDF 2,000 — and audited
  (`role.exported` / `permission.exported`).
- Cells neutralise spreadsheet formula prefixes; no PII is exported (roles/permissions carry none).

### US-09 — Flush the permission cache

As a Super Admin I can invalidate Spatie's cached permission registry after an out-of-band change.

**Acceptance criteria**

- `POST /access-control/cache-reset` requires the access capability and `UPDATE_PERMISSION`.
- It calls `PermissionRegistrar::forgetCachedPermissions()`, audits `access.cache_reset`, and redirects
  back with a status message (204 for JSON).

### US-10 — Authorization and audit

**Acceptance criteria**

- Every route is behind `auth`, `verified`, `can:manage-access`, the module throttle, and its
  `permission:*` middleware.
- `manage-access` is true only for an **active** `SUPER_ADMIN`; a delegated user holding every
  fine-grained permission still receives 403.
- Guest requests redirect to login (401 for JSON).
- Every write and export writes exactly one activity-log row with `log_name = access-control`.
- 422 responses never mutate the catalogue.

## Out of scope (written, not implied)

- **Teams.** `config('permission.teams')` is false; no `team_foreign_key`.
- **Guards.** The module manages the `web` guard only; the UI does not expose `guard_name`.
- **Direct user grants.** `Users` owns user↔role/permission assignment.
- **Role hierarchy / delegated role administration.** The gate is Super Admin only. Fine-grained
  permissions are defence in depth, not a delegation feature.
- **Soft deletes, restore, bulk selection.** Spatie's `roles`/`permissions` tables have no soft-delete
  contract; adding one would change authorization queries. The UI therefore exposes no row selection,
  so the "bulk delete + bulk restore" rule does not apply.
- **Permission groups table.** Groups are derived from the `{ACTION}_{MODULE}` name; no schema.
- **A second cache.** Spatie owns the permission cache; the module only invalidates it.
- **Changing the seeded role/permission matrix.** The seeder remains the catalogue source.

## Assumptions accepted in Phase 2

| #   | Assumption                                                                                             | Why                                                                                                                                           |
| --- | ------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- |
| A1  | One module, two resources                                                                              | See decision above.                                                                                                                           |
| A2  | `permissions.is_system` is added; existing rows are backfilled `true`                                  | The 514 seeded permissions are code-referenced capabilities; renaming/deleting them would break routes. A flag is the only explicit boundary. |
| A3  | System roles are the two names in `Shared\Domain\Enums\Role`                                           | Those are the only role names the application checks in code; other seeded roles are data.                                                    |
| A4  | Custom names must match `^[A-Z][A-Z0-9_]{2,63}$`; the request normalises to uppercase                  | Keeps URLs, route middleware strings and the seeded catalogue consistent.                                                                     |
| A5  | Only active `SUPER_ADMIN` reaches the surface                                                          | Same posture as Users (`can:manage-staff`); the access catalogue is installation-level.                                                       |
| A6  | A role/permission assigned to users/roles cannot be deleted                                            | Prevents silent privilege loss. Renaming is allowed for custom rows because assignments are by id.                                            |
| A7  | Exports are synchronous; CSV/XLSX are capped at 10,000 rows, PDF at 2,000 (2026-11-24)                 | Matches Users; a queued export is a separate feature. PDF is rendered in-request by dompdf, so it gets the lower ceiling.                     |
| A8  | The role edit screen may change system-role permission sets but never their names                      | `SUPER_ADMIN` must keep every capability; the matrix is exactly how an operator repairs that.                                                 |
| A9  | No `show` route                                                                                        | The list + edit page carry every field; an extra read-only page would be a presumptive feature.                                               |
| A10 | Spatie is upgraded from 8.3.2 to 8.5.0 (latest, 2026-11-06, no breaking changes) before implementation | The feature depends on the package and the release notes document no API break; the lockfile is updated and the full suite re-run.            |

## Functional requirements

| ID    | Requirement                                                                                                          | Stories      |
| ----- | -------------------------------------------------------------------------------------------------------------------- | ------------ |
| FR-01 | Routes require `auth`, `verified`, `can:manage-access`, `throttle:60,1` and the fine-grained `permission:*`.         | US-10        |
| FR-02 | `permissions.is_system` exists, defaults `false`, and every pre-existing row is backfilled `true`.                   | US-06, US-07 |
| FR-03 | The seeder re-protects every catalogue name with `is_system = true` and keeps idempotent role creation.              | US-06, US-07 |
| FR-04 | Role create validates the uppercase identifier, uniqueness per guard, and a bounded list of existing permissions.    | US-02        |
| FR-05 | Role update syncs (replaces) the permission set and refuses a system-role rename while allowing its permission sync. | US-03        |
| FR-06 | Role delete refuses system roles and roles assigned to users, and succeeds only for unassigned custom roles.         | US-04        |
| FR-07 | Role writes run in a transaction, audit one event and flush the permission registry.                                 | US-02…US-04  |
| FR-08 | Role list supports search, type/permission/date filters, allowlisted sort, pagination and `withCount` aggregates.    | US-01        |
| FR-09 | Role edit returns the role plus the full permission catalogue in one query.                                          | US-03        |
| FR-10 | Permission list supports search, type/group/role/date filters, allowlisted sort, pagination and `withCount`.         | US-05        |
| FR-11 | Permission create always stores `is_system = false` and validates the uppercase unique name.                         | US-06        |
| FR-12 | Permission update refuses system rows and allows a custom rename while preserving id-based grants.                   | US-07        |
| FR-13 | Permission delete refuses system rows and any row assigned to a role or user.                                        | US-07        |
| FR-14 | Permission writes run in a transaction, audit one event and flush the permission registry.                           | US-06, US-07 |
| FR-15 | `POST /access-control/cache-reset` flushes the registry and audits `access.cache_reset`.                             | US-09        |
| FR-16 | Role export supports CSV/XLSX/PDF, the same filters as index, both permissions, a 10k cap and audit.                 | US-08        |
| FR-17 | Permission export mirrors FR-16 for the permission list.                                                             | US-08        |
| FR-18 | The React screens use `DataTableKit`, `useCrudTable`, `CrudFilters`, `CrudDialog` and `useZodForm`; no hand types.   | US-01…US-07  |
| FR-19 | Roles and Permissions are navigation destinations gated by the shared `canManageAccess` capability.                  | US-10        |
| FR-20 | Feature tests cover 401/403/422 and happy paths; unit tests cover filters and name grouping; FLAT mode is declared.  | US-01…US-10  |

## Gate — Phase 1

- Every user story has acceptance criteria: **yes** (US-01…US-10).
- Out of scope written: **yes**.
- Ambiguity resolved: one module; system/custom boundary; deletion guards; no bulk/restore.
