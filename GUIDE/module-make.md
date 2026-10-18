# GUIDE — `npm run module:make` (JSON → FLAT simple-CRUD scaffolding)

> Usage guide, not rules. Rules live in `.opencode/skills/` (canonical).
> Script: `scripts/module-make.mjs` · Example spec: `stubs/module/module.spec.example.json`.

## When to use it (and when NOT to)

USE for a **simple FLAT CRUD**: 1 aggregate, 3–8 fields, list/show/create/update/
delete/restore, optional `user_id` ownership, bulk pair, CSV/Excel/PDF export, optional
Sanctum API. The SSD plan (§4 data model) carries the fields as JSON.

DO NOT use for anything with state machines, multi-step lifecycles, ≥2 third-party
integrations in one module, cross-module orchestration, files/media pipelines —
that's `/backend-new` (PROGRESSIVE) and needs human preparation. The script
prints the STOP warning itself; obey it.

### Declare the lifespan — this generator is for the `module` lifespan

`"lifespan": "module"` opts into the full ceremony: `Data/`, `FilterData`, ownership scope,
the 401/403/422 matrix, the bulk pair, and exports on request.

`"lifespan": "one-shot"` is **refused**, and that is the point. An Artisan command, a migration or
a seeder is not a module. It still gets `declare(strict_types=1)`, explicit types, a Pest test and
Pint. It does **not** get Data classes, a FilterData, exports, policies, a service provider,
ownership scoping or the 401/403/422 matrix. Handing a one-line command that ceremony is the
speculative build YAGNI forbids — every piece individually defensible, collectively a folder nobody
asked for.

If it will live for months and be read by someone else, declare `module` and come back here.
Rationale: `PRINCIPLES/SKILL.md` §6.

## The flow (two doors, same generator)

```
SSD PLAN (§4) → specs/{name}.spec.json → npm run module:make → review → migrate → test
```

**Manual door (you place the attributes, npm does the scaffolding):**

```bash
# 1. drop the JSON in the inbox (copy specs/_example.spec.json)
# 2. preview (writes nothing)
npm run module:make -- --dry-run

# 3. generate the whole inbox (skips existing modules + _* files)
npm run module:make
```

**AI door (single spec from the plan):**

```bash
npm run module:make -- path/to/module.spec.json --dry-run
npm run module:make -- path/to/module.spec.json   # refuses existing dir unless --force
```

**Close the gate** (see `.opencode/commands/backend-new-crud.md` PHASE 4-5):

```bash
composer dump-autoload
php artisan migrate
php artisan test --compact --filter={Module}
```

## Spec schema

| Key           | Required | Meaning                                                                                                                                                                |
| ------------- | -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `module`      | yes      | PascalCase (`Labels`) → `src/Modules/Labels/`                                                                                                                          |
| `entity`      | yes      | PascalCase singular (`Label`)                                                                                                                                          |
| `table`       | yes      | snake_plural (`labels`)                                                                                                                                                |
| `route`       | yes      | kebab-plural URL prefix (`labels`)                                                                                                                                     |
| `userOwned`   | no       | default `true`: `user_id` FK + `user()` + inverse on `User`                                                                                                            |
| `softDeletes` | no       | default `true`: `deleted_at` + restore routes + status filter                                                                                                          |
| `bulk`        | no       | default `true`: paired bulk-delete + bulk-restore                                                                                                                      |
| `api`         | no       | default `false`: separate `Api/` controller + `Routes/api.php` (Sanctum)                                                                                               |
| `exports`     | no       | `["csv","xlsx","pdf"]` — any subset. Each adds nothing to the module beyond the shared `ExportPort`; `pdf` also writes a Blade table view. Unknown format = hard error |
| `permissions` | no       | prefix, default = table uppercased (`LABELS`)                                                                                                                          |
| `fields[]`    | yes      | `{name, type, …}` — max 8 for simple CRUD (warns above)                                                                                                                |

Field: `{ "name": "name", "type": "string", "length": 120, "required": true,
"unique": true, "searchable": true }`.
Types: `string(length) | text | integer | boolean | date | datetime |
decimal(precision,scale) | uuid | json | foreign(references)`.
`required: false`/`nullable: true` → nullable column + `nullable` rule.
`foreign` needs `"references": "<table>"` — **that table must already exist**
(migration order), otherwise inserts fail with FK violations.

## What it writes (15+ files) and patches (2, idempotent)

```
src/Modules/{Module}/
  ARCHITECTURE.md            mode: FLAT (filled, from stubs/architecture/)
  Models/{Entity}.php        uuid + user() belongsTo, scopeApplyFilters, LogsActivity
                             + scopeOwnedBy/assertOwned when userOwned (see below)
  Data/{Entity}Data.php      Spatie Data (Inertia props AND JSON — the TS source)
  Data/{Entity}FilterData.php  canonical search/status/date/sort/page shape
  Http/Controllers/{Entity}Controller.php      fused web+JSON (expectsJson branch)
  Http/Controllers/Api/{Entity}Controller.php  JSON-only, explicit return types (= Scramble docs)
  Http/Controllers/{Entity}ExportController.php (with exports)
  Http/Requests/Store|Update|BulkDelete|BulkRestore{Entity}Request.php
  Exports/{Entity}ExportTransformer.php       column map + lazy owner-scoped rows
  resources/views/exports/{table}.blade.php   PDF table markup (with pdf)
  Http/Controllers/{Entity}ExportController.php  thin: resolves ExportPort, dispatches format
  Routes/web.php             auth + permission:* + whereUuid, /export/{format} before /{uuid}
  Routes/api.php             (with api: sanctum + throttle:60,1)
  Providers/{Module}ServiceProvider.php
  Tests/Feature/{Entity}CrudTest.php  401 + 403-permission + 403-non-owner + 422
                             + CRUD/restore + bulk + every export format → green on sqlite :memory:
database/migrations/*_create_{table}_table.php   id + uuid + user_id? + fields + composite index
database/factories/{Entity}Factory.php           factory output always passes Store validation
PATCH  app/Models/User.php                       inverse hasMany (userOwned only, duplicate-guarded)
PATCH  bootstrap/providers.php                   module provider (duplicate-guarded)
PRINT  seeder snippet                            VIEW/CREATE/UPDATE/DELETE[/RESTORE][/EXPORT]_X
```

## Ownership (BOLA) — what `userOwned: true` actually generates

`userOwned` is **not** just a `user_id` column. It generates the object-level authorisation
(OWASP API1:2023), and that is the part that matters:

- `scopeOwnedBy(int|string|null $ownerKey)` on the model — the single choke point
- `assertOwned(?self $row, $ownerKey)` — 404 if absent, **403** if someone else's
- `index` and the export are **owner-scoped**, so one user's rows are never in another's list or CSV
- `show` / `edit` / `update` / `destroy` / `restore` all load through `assertOwned`
- `bulk-delete` / `bulk-restore` refuse the whole request unless **every** requested uuid is owned
- a Feature test proves each of the above, including that the row survives the refused requests

`permission:*` authorises the **action**, never the **row**. A uuid is unguessable, not secret. If you
hand-write a `userOwned` module instead of generating it, `assertOwned` is the first thing you owe it.

`userOwned: false` generates none of this — correct only for a genuinely global entity. A
shared-but-restricted entity needs a real policy, not a global table.

Verified 2026-09-28: generating a `userOwned` module and deleting the ownership check turns the
non-owner test red with `Expected 403 but received 200`. The test has teeth.

## A patch that changed nothing once told you it had

`app/Models/User.php` ended with `}\n`, so the old `replace(/\n\}$/, $method)` never matched. The
patch still returned `changed: true`, wrote an identical file, and Pint then deleted the two imports
it had added — so the inverse `hasMany` silently never existed while the log said `patched`.

Both patches now **fail loudly** when a replacement does not match, and both are guarded so a second
run reports `already ok` instead of appending a duplicate. If you see `already ok` twice, that is
idempotency working; if you see a `no match` error, the target file's shape changed and the patch
needs updating rather than skipping.

## Exports — the module ships a transformation, the port ships the mechanics

`"exports": ["csv", "xlsx", "pdf"]` — all three are served by the project's own
`Shared\Domain\Ports\ExportPort` (bound to `SimpleExcelExportAdapter`, which delegates PDF to
`DomPdfExportAdapter`). An unknown format is a **hard error**, not a silent skip.

The module contributes exactly two things:

| Module file                                 | Owns                                                 |
| ------------------------------------------- | ---------------------------------------------------- |
| `Exports/{Entity}ExportTransformer.php`     | the column map + a lazy, **owner-scoped** row stream |
| `resources/views/exports/{table}.blade.php` | the PDF table markup (only when `pdf` is requested)  |

Plus a thin `Http/Controllers/{Entity}ExportController.php` that resolves the port and dispatches
on format, and one route: `GET /export/{format}` constrained with `whereIn`.

**Nothing else.** No `fopen('php://output')`, no `fputcsv`, no `SimpleExcelWriter::create()` in a
module — that is what the port exists for, and the previous version of this generator reimplemented
`ExportPort::tabular()` by hand, duplicated the owner scope in a second place, and made xlsx/pdf
unreachable. Its docblock says it outright: _"A module re-implementing the writer is a DRY FAIL."_

Verified 2026-09-28 on a generated module — all three formats produce real binary output:

```
bytes: csv=67  xlsx=4478  pdf=877966
xlsx magic = 504b0304      (PK\x03\x04 — a real ZIP/XLSX container)
pdf  head  = %PDF-1.7      and contains %%EOF
```

`tabular()` picks its driver from the filename extension, so adding a format costs nothing in the
module. The export applies the same `{Entity}FilterData` and the same `permission:EXPORT_*` as
`index`, and the same owner scope — an export that widens either is a data leak, and the generated
test asserts it does not.

## Scramble (OpenAPI)

No annotations to write. Scramble documents the API from: explicit return
types on the `Api/` controller (`JsonResponse`, `Response`), injected
`FormRequest::rules()`, and Spatie `Data` property types. After generation:
`php artisan scramble:export` (regenerates `api.json`). Web fused-controller
actions are intentionally NOT the documented surface — the `Api/` controller is.

## CSV export contract

Same `FilterData`, same owner scope, and the same permission family as the list: a filtered export
exports exactly what is on screen and nothing more. See the **Exports** section above — the module
never writes a stream itself.

## Frontend

Intentionally NOT generated. Build it with `/frontend-new`:
`Pages/{Module}/Index|Show|Create|Edit` + Colada queries + Zod schemas.
Backend `Data` classes are the single source — run
`php artisan typescript:transform` before writing any `.vue`.
