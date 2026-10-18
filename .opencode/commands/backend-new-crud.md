---
description: Generates a simple FLAT CRUD module (1 aggregate, 3-8 fields) from a JSON spec via `npm run module:make`, then verifies it against the rules. Not for anything with invariants — use /backend-new or /ssd-artifact.
---

# BACKEND NEW — simple FLAT CRUD

Not an agent that writes files. The **generator** writes files; this command produces the spec,
reviews the result and runs the gate.

## 1. Qualify — do not continue unless all are true

- One aggregate / one main entity, roughly 3-8 persisted fields
- No state machine, no multi-step lifecycle, no invariant beyond validation
- CRUD + soft delete/restore, optional `user_id` ownership
- No file/media pipeline, no third-party integration, no cross-module orchestration
- **It will live for months and be read by someone else** — i.e. `"lifespan": "module"`

The lifespan test is not a formality. If the artifact is an Artisan command, a migration or a
seeder, this generator is the wrong tool and the spec's `lifespan: one-shot` makes it refuse with
guidance. The full ceremony — Data, FilterData, ownership, 401/403/422, bulk pair — is the price of
code that lasts. `PRINCIPLES/SKILL.md` §6.

Anything else → `/backend-new` (PROGRESSIVE) or `/ssd-artifact` (scope not agreed). The generator
prints a STOP warning of its own; obey it rather than working around it.

## 2. Read, in this order

1. `GUIDE/module-make.md` — the spec schema and the exact file list
2. `.opencode/skills/backend-php/SKILL.md` — PHP 8.5 + Laravel 13 rules
3. `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md` §1 and §4 — the FLAT tree and why `Data/` exists
4. `.opencode/skills/PRINCIPLES/SKILL.md` §1 — before you decide anything needs extracting
5. `.opencode/skills/owasp/SKILL.md` + `STACK.md` — always-on, both halves

## 3. Produce the spec, then generate

```bash
# copy specs/_example.spec.json, fill in ONLY the attributes
npm run module:make -- specs/{name}.spec.json --dry-run   # preview, writes nothing
npm run module:make -- specs/{name}.spec.json             # generate
```

## 4. Review the output against the rules — do not trust it

- [ ] The generated `ARCHITECTURE.md` says `mode: FLAT` and its "accepted debt" section is honest
- [ ] `Data/{Entity}Data.php` + `Data/{Entity}FilterData.php` exist — they are the source of the frontend types and the export contract
- [ ] The Feature test covers **401, 403 and 422** plus the happy path, restore and bulk pair
- [ ] `userOwned: true` really does 403 a non-owner; `userOwned: false` has a stated reason
- [ ] Export applies the same filters and the same `permission:EXPORT_*` as `index`
- [ ] No `Resources/` folder, no `JsonResource` subclass
- [ ] `bootstrap/providers.php` and the `User` inverse relation were patched, not duplicated

## 5. Regenerate the types, then close

```bash
npm run module:codegen     # required: the module adds Data classes
```

## 6. Report

5 lines: what was generated, the field list, the test coverage, anything the generator refused to do,
and the gate result. Never more.
