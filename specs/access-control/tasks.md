# Access Control — Tasks

Every task maps to at least one user story and one functional requirement. The final table is the
traceability contract consumed by Phase 7.

## T-01 — Dependency and migration baseline

- Upgrade `spatie/laravel-permission` to `^8.5` (lockfile) and `web-auth/webauthn-lib` to `^5.3.13`
  (security advisories), run the suite.
- Add `database/migrations/2026_11_24_000004_add_is_system_to_permissions_table.php` (backfill `true`).
- Update `database/seeders/RolePermissionSeeder.php` to `updateOrCreate` with `is_system = true`.
- **Stories:** US-06, US-07; **FR:** FR-02, FR-03.

## T-02 — Shared authorization vocabulary

- Add the 14 access-control cases to `Shared\Domain\Enums\Permission`.
- Add `Support/PermissionName.php` (regex + group derivation).
- **Stories:** US-10; **FR:** FR-01.

## T-03 — Module skeleton and provider

- `src/Modules/AccessControl/Providers/AccessControlServiceProvider.php`: routes, `manage-access` Gate,
  `access-control-exports` rate limiter.
- Register in `bootstrap/providers.php`.
- **Stories:** US-10; **FR:** FR-01.

## T-04 — Input DTOs and FormRequests (roles)

- `RoleInputData`, `StoreRoleRequest`, `UpdateRoleRequest`.
- Trim/uppercase normalisation, uniqueness per guard, bounded permission array, 422 mapping.
- **Stories:** US-02, US-03; **FR:** FR-04, FR-05.

## T-05 — Role actions

- `CreateRoleAction`, `UpdateRoleAction`, `DeleteRoleAction`.
- Transaction, lock, system-role guard, assigned-users guard, cache flush, audit.
- **Stories:** US-02, US-03, US-04; **FR:** FR-04…FR-08.

## T-06 — Role reads

- `RoleData`, `RoleDetailData`, `RoleFilterData`, `RoleListQuery`, `PermissionCatalogQuery`.
- **Stories:** US-01, US-03; **FR:** FR-09, FR-10.

## T-07 — Role HTTP surface

- `RoleController` (`index`, `create`, `store`, `edit`, `update`, `destroy`), `Routes/web.php`.
- JSON/Inertia dual transport, abilities, 401/403/404/422.
- **Stories:** US-01…US-04; **FR:** FR-11.

## T-08 — Permission input and actions

- `PermissionInputData`, `StorePermissionRequest`, `UpdatePermissionRequest`,
  `CreatePermissionAction`, `UpdatePermissionAction`, `DeletePermissionAction`,
  `ResetPermissionCacheAction`.
- System-row guard, assignment counts, transaction, cache, audit.
- **Stories:** US-06, US-07, US-09; **FR:** FR-12, FR-13.

## T-09 — Permission reads

- `PermissionData`, `PermissionOptionData`, `PermissionCatalogData`, `PermissionFilterData`,
  `PermissionListQuery`, `FilterOptionsQuery`.
- **Stories:** US-05; **FR:** FR-14.

## T-10 — Permission HTTP surface

- `PermissionController` (`index`, `store`, `update`, `destroy`), `PermissionCacheController`.
- **Stories:** US-05…US-07, US-09; **FR:** FR-15.

## T-11 — Exports

- `RoleExportTransformer`, `PermissionExportTransformer`, export controllers, Blade views.
- Same filters/queries/permissions; 10k cap; audit.
- **Stories:** US-08; **FR:** FR-16, FR-17.

## T-12 — Frontend types and schemas

- Generate `Modules.AccessControl.Data.*` via `typescript:transform`.
- Add `resources/js/Modules/AccessControl/schemas/{role,permission}.ts`.
- **Stories:** US-02, US-03, US-06, US-07; **FR:** FR-18.

## T-13 — Roles UI

- `Roles/Index.tsx`, `Roles/Create.tsx`, `Roles/Edit.tsx`, `RoleForm.tsx`, `PermissionMatrix.tsx`.
- `DataTableKit`, `useCrudTable`, `CrudFilters`, `CrudDialog`, `useZodForm`, `StickyFormBar`.
- **Stories:** US-01…US-04, US-09; **FR:** FR-18, FR-19.

## T-14 — Permissions UI

- `Permissions/Index.tsx`, `PermissionDialog.tsx`.
- Create/edit/delete dialogs, filters, export, action error.
- **Stories:** US-05…US-07; **FR:** FR-18, FR-19.

## T-15 — Navigation and capabilities

- Share `canManageAccess` from `HandleInertiaRequests`.
- Add Roles/Permissions entries to `lib/navigation.ts`; thread the capability through `AppLayout` and
  `AppSidebar`.
- **Stories:** US-10; **FR:** FR-20.

## T-16 — Tests

- Feature: `RoleCrudTest`, `PermissionCrudTest`, `AccessControlExportTest`, `AccessControlGateTest`,
  `AccessControlPagesTest`.
- Unit: `RoleFilterDataTest`, `PermissionFilterDataTest`, `PermissionNameTest`.
- Fixtures: `AccessControlFixtures`.
- **Stories:** all; **FR:** all.

## T-17 — Documentation and closing gate

- `src/Modules/AccessControl/ARCHITECTURE.md` (mode FLAT, invariants, accepted debt).
- `npm run module:codegen`, `npm run module:done`, graph re-index and coverage check.
- **Stories:** all; **FR:** all.

---

## Phase 5 — Analyze verdict

The plan satisfies the spec before any code is written:

| Check                                                                  | Result |
| ---------------------------------------------------------------------- | ------ |
| Every user story maps to ≥1 task                                       | Pass   |
| Every functional requirement maps to ≥1 task                           | Pass   |
| Every route has auth + permission + throttle                           | Pass   |
| Every write has transaction + audit + cache flush                      | Pass   |
| Every list has search, filters, sort, pagination and an export sibling | Pass   |
| No N+1: aggregates use `withCount`, matrix uses one query              | Pass   |
| No new abstraction without a second consumer (no repository, no port)  | Pass   |
| Out-of-scope items have no task                                        | Pass   |
| System/custom boundary is testable without the UI                      | Pass   |

**Gaps found and resolved before implementation**

1. The seeded `RESTORE_ROLE`/`BULK_RESTORE_ROLE` names imply a soft-delete feature that Spatie does not
   own → documented as out of scope; no row selection, therefore no bulk pair.
2. The permission `group` filter could use a `LIKE '%_GROUP'` pattern where `_` is a wildcard → use an
   explicit suffix predicate in `PermissionListQuery` and validate `group` as an uppercase identifier.
3. The role index needs permission names for its filter → one `pluck('name')` in `FilterOptionsQuery`,
   not a second catalogue query per row.

Verdict: **proceed to Phase 6.**
