# Access Control — Traceability

- **Date:** 2026-11-24
- **Spec:** `specs/access-control/spec.md`
- **Plan:** `specs/access-control/plan.md`

## User story → code → test

| Story | Code                                                                                                                                                                  | Tests                                                                                                                                             |
| ----- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| US-01 | `RoleController@index`, `RoleListQuery`, `RoleFilterData`, `RoleData`, `Pages/AccessControl/Roles/Index.tsx`, `lib/crud-table.ts`                                     | `RoleCrudTest` (index/filters/sort/JSON), `RoleFilterDataTest`, `AccessControlPagesTest`                                                          |
| US-02 | `StoreRoleRequest`, `RoleInputData`, `CreateRoleAction`, `RoleController@store`, `Roles/Create.tsx`, `RoleForm.tsx`                                                   | `RoleCrudTest` (create/duplicate/invalid/audit)                                                                                                   |
| US-03 | `UpdateRoleRequest`, `UpdateRoleAction`, `RoleController@edit/update`, `Roles/Edit.tsx`, `RoleForm.tsx`, `PermissionMatrix.tsx`                                       | `RoleCrudTest` (update, system rename refused, system permission sync), `AccessControlPagesTest` (edit payload)                                   |
| US-04 | `DeleteRoleAction`, `RoleController@destroy`, `Roles/Index.tsx` delete dialog                                                                                         | `RoleCrudTest` (custom success, system refused, assigned refused)                                                                                 |
| US-05 | `PermissionController@index`, `PermissionListQuery`, `PermissionFilterData`, `PermissionData`, `Permissions/Index.tsx`, `FilterOptionsQuery`                          | `PermissionCrudTest` (index/group/role filters/JSON), `PermissionFilterDataTest`, `AccessControlPagesTest`                                        |
| US-06 | `StorePermissionRequest`, `PermissionInputData`, `CreatePermissionAction`, `PermissionDialog.tsx`                                                                     | `PermissionCrudTest` (custom create, duplicate, cache)                                                                                            |
| US-07 | `UpdatePermissionRequest`, `UpdatePermissionAction`, `DeletePermissionAction`, `PermissionDialog.tsx`, `Permissions/Index.tsx`                                        | `PermissionCrudTest` (system rename/delete refused, assigned delete refused, custom rename/delete)                                                |
| US-08 | `RoleExportController`, `RoleExportTransformer`, `PermissionExportController`, `PermissionExportTransformer`, `resources/views/exports/{roles,permissions}.blade.php` | `AccessControlExportTest` (CSV/XLSX/PDF, filters, permission pairs, audit)                                                                        |
| US-09 | `ResetPermissionCacheAction`, `PermissionCacheController`, `Routes/web.php`, both index screens' "Flush cache" button                                                 | `RoleCrudTest` (cache reset + audit), `PermissionCrudTest` (cache contains new permission)                                                        |
| US-10 | `AccessControlServiceProvider` (`manage-access`), `Routes/web.php`, `HandleInertiaRequests`, `lib/navigation.ts`, `AppLayout.tsx`, `AppSidebar.tsx`                   | `AccessControlGateTest` (guest, delegated, inactive, stripped), audit assertions in `RoleCrudTest`/`PermissionCrudTest`/`AccessControlExportTest` |

## Functional requirement → task

| FR    | Task(s)          |
| ----- | ---------------- |
| FR-01 | T-03, T-07, T-10 |
| FR-02 | T-01             |
| FR-03 | T-01             |
| FR-04 | T-04             |
| FR-05 | T-05             |
| FR-06 | T-05             |
| FR-07 | T-05             |
| FR-08 | T-06             |
| FR-09 | T-06             |
| FR-10 | T-09             |
| FR-11 | T-08             |
| FR-12 | T-08             |
| FR-13 | T-08             |
| FR-14 | T-08             |
| FR-15 | T-08, T-10       |
| FR-16 | T-11             |
| FR-17 | T-11             |
| FR-18 | T-12, T-13, T-14 |
| FR-19 | T-15             |
| FR-20 | T-16, T-17       |

## Definition of done

1. Every user story has code and a test: **yes** (table above).
2. Every FR is implemented or explicitly dropped: **yes** (table above; no drops).
3. `src/Modules/AccessControl/ARCHITECTURE.md` matches disk: **yes** (FLAT; no Domain/Application/Infrastructure).
4. `npm run module:done`:
   - `format`, `syn`, `tsc --noEmit`, `vite build`, PHPStan L5, Pint (Blade), Pest: **green**.
   - `laravel/doctor`: **21/22 passed, 1 environmental failure** — `Composer autoload works` times
     out at 60 s on this Windows box. This is the failure already recorded in
     `.opencode/rules/rules.md` §"Known gate state — verified 2026-11-23"; a manual
     `composer dump-autoload --optimize --strict-psr --strict-ambiguous` exits 0, and
     `composer audit` is clean. The doctor diagnostic is not a code finding.
5. `composer codegen` (ide-helper, Pint, `typescript:transform`, Scramble): **ran**; generated types
   and `api.json` refreshed.
6. Graph re-indexed (`full` + `fast`) and `check_index_coverage` on the module scopes and all shared
   edited files: **no recorded issue** (best-effort; `metadata_changed` on uncommitted files).
