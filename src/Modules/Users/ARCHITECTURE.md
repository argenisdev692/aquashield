# Users — ARCHITECTURE.md (FLAT variant, the default)

> Copy of `stubs/architecture/ARCHITECTURE.FLAT.md`, filled in.
> Full rules → `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`.
> `tests/Architecture/ModuleComplexityTest.php` parses the `mode:` line.

mode: FLAT

## Why FLAT

One aggregate (`App\Models\User`) plus vendor-owned Role/Permission/activitylog.
The module's one real invariant is the privilege ceiling, and it fits in a
policy plus a single support class — it does not justify `Domain/`,
`Application/` and `Infrastructure/`. Hexagonal layering here would make every
CRUD change touch nine files with no test that fails if a layer is deleted.

## Triggers evaluated (1 of 4 — FLAT)

- [ ] **≥3 models with business rules** — the module owns one aggregate. `Role`
      and `Permission` are Spatie models, and `activitylog` owns the audit
      persistence; `SKILL-ARCHITECTURE.md` §4 lists both as "never" for
      PROGRESSIVE.
- [x] **Invariants** — the privilege ceiling: an actor cannot grant a role or
      permission they do not themselves hold, cannot edit/delete a user above
      their effective permissions, cannot change their own access, and cannot
      remove the last `SUPER_ADMIN`. Enforced by `Policies/UserPolicy` +
      `Support/AccessCeiling`, not by a `Domain/` layer.
- [ ] **Tests that substitute persistence/external** — the permission tables
      _are_ the behaviour; mocking them would assert the mock. The invitation
      job is the only outbound call, and it is tested with `Queue::fake()`.
- [ ] **Roadmap: extraction to a microservice** — users are the identity root
      (Fortify, 2FA, passkeys, sessions); extraction would drag auth with it.

## Boundaries and public contracts

- Response/input shapes: `Data/` (Spatie Data). `ManagedUserData` is the
  allowlisted admin payload; `CreateUserData`/`UpdateUserData` are inputs.
- Row-level rule: `Policies/UserPolicy` (ceiling, self-protection, last super
  admin). This is the declared exception to the "no per-module policy" default:
  no `permission:*` string can express it.
- Grant-set rule: `Support/AccessCeiling`. One definition consumed by the
  policy and by the create/assign actions.
- Read contract: `Queries/UserListQuery` is shared by `index` and all three
  export formats, so a filter cannot exist on screen but be missing from a CSV.
- Export mechanics: `Shared\Domain\Ports\ExportPort` only. The module ships
  `Exports/UserExportTransformer` + `resources/views/exports/users.blade.php`.
- Audit: `Shared\Domain\Ports\AuditPort` (explicit actions). No `LogsActivity`
  on `User`: the auth model is updated by Fortify too, and passive logging there
  would mix self-service profile edits with administrative actions.
- Job: `Jobs/SendUserInvitationJob` (queued password-reset link, dispatched
  after commit).
- What is NEVER exposed: the persistence model, `id`, password hashes,
  remember tokens, Google ids, 2FA secrets.

## Applicable invariants (see skill §3)

1. Controllers validate transport input, invoke one use case (or one read
   query), and map the response — `Http/Controllers/UserController.php`.
2. No `Domain/`, `Application/`, `Infrastructure/` folders (the architecture
   test fails them for a FLAT module).
3. Responses are Spatie `Data`, never `JsonResource`.
4. Bulk delete + bulk restore ship together, both tested.
5. Every class has a consumer — no speculative folder.

## Why the optional folders exist

- `Policies/` — the skill permits a module policy only when it owns a rule a
  permission string cannot express; the ceiling is exactly that.
- `Queries/` — `index` and the export must share one filter definition (DRY
  knowledge, not shape). `UserAccessCatalogQuery` keeps the assignment screen's
  read out of the controller.
- `Support/` — the ceiling has three consumers (policy + two assign actions, and
  the create action), so it is a named rule, not a helper folder.
- `Jobs/` — the invitation is an external call that must not run inside the
  request or the transaction.

## Accepted debt

- `User` stays in `app/Models` because it is the Fortify/auth aggregate. The
  module operates on it instead of moving it and dragging auth across the tree.
- `SUPER_ADMIN` appears in `Support/AccessCeiling` and in the seeder. That is the
  second occurrence; per the Rule of Three it moves to a Shared enum when a
  third consumer appears.
- No custom cache. spatie/laravel-permission caches the permission registry for
  24 hours and flushes it on every role/permission change; a second cache would
  need a second invalidation path.
- Exports are synchronous and capped at 10,000 rows. A queued export is a
  separate feature; the cap reports rather than truncates.
- Admin-created/changed accounts are e-mail-verified by the administrator. This
  is deliberate for an internal staff workflow; a verification wall would lock
  the new user out of the screens they were created for.
- Inertia pages are not part of this backend task. The JSON contract (`rows` +
  `total`, `filter[...]`, `sort`, `per_page`) is the DataTable kit's contract.

## Tests holding the module down

- `Tests/Feature/UserCrudTest.php` — 401/403/422, index/filter/sort, show,
  create, update, delete/restore, bulk pair, audit rows.
- `Tests/Feature/UserAccessTest.php` — catalog, role/direct-permission
  assignment, the Juan/Manager scenario, self-protection, last super admin.
- `Tests/Feature/UserExportTest.php` — CSV/XLSX/PDF, same filters as the list,
  403 without `EXPORT_USER`.
- `Tests/Unit/AccessCeilingTest.php` — the ceiling and last-super-admin rule
  called directly, including the branch the HTTP self-check masks.
