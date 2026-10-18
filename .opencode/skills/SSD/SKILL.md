---
name: ssd
description: Spec-Driven Development for a new backend module, service or API. Use when the request is a new non-trivial module, a multi-step flow, or anything whose scope is still ambiguous — not for simple CRUD, which has a generator. Runs 7 phases, each producing a file the next one consumes, with mandatory real-time research before the technical plan.
---

# SSD — Spec-Driven Development

> Simple FLAT CRUD does **not** come here. It has a generator: `GUIDE/module-make.md`.
> Use this when the module has invariants, a multi-step flow, cross-module orchestration, or the scope
> is not yet agreed.

## The golden rule

**Never skip from spec straight to code.** Review the plan before breaking down tasks; review the
tasks before implementing. Do not merge phases. Each phase produces a Markdown file that the next
phase consumes.

If the user asks for "just one part" (e.g. "only make me the plan"), still use the same format for that
phase — and say explicitly which earlier phases you assumed or are missing.

## The 7 phases

| Phase | Name            | Produces                  | Template                | Gate before moving on                                                               |
| ----- | --------------- | ------------------------- | ----------------------- | ----------------------------------------------------------------------------------- |
| 1     | **Specify**     | `spec.md`                 | `SSD/spec_template.md`  | every user story has acceptance criteria; out-of-scope is written                   |
| 2     | **Clarify**     | edits `spec.md`           | —                       | zero `[NEEDS CLARIFICATION]` items remain, or the user accepted them as assumptions |
| 3     | **Plan**        | `research.md` + `plan.md` | `SSD/plan_template.md`  | every stack version in the plan is cited from real research, not memory             |
| 4     | **Tasks**       | `tasks.md`                | `SSD/tasks_template.md` | every FR and every user story maps to ≥1 task                                       |
| 5     | **Analyze**     | verdict                   | —                       | the plan and tasks still satisfy the spec; report gaps before writing code          |
| 6     | **Implement**   | the code                  | —                       | closing gate exits 0                                                                |
| 7     | **Consolidate** | updated docs              | —                       | `ARCHITECTURE.md` matches disk; traceability holds                                  |

**Research is a mandatory step inside Phase 3, not a phase of its own.** The plan is only as good as
the version information behind it, and version information is exactly what training data gets wrong.
Before writing `plan.md`:

- **Tavily** for current versions and release state. Use `time_range: "month"` (or explicit
  `YYYY-MM-DD` bounds) whenever the question implies _now_. Cite the source in the plan's
  "Verified version" column — a version with no source is an assumption.
- **Context7** for how an installed package's API actually works, scoped to the installed major.
- **Never** research a package from memory to produce a version string. Check `composer show` or
  `package.json` for what is _installed_; research only for what _changed_.

Watch for the traps this repo already documents: PrimeVue 5 deprecates rather than breaks, and
context7's PrimeVue docs are v4; Vue 3.6 is still an RC; TypeScript 6.0 is a bridge to the native 7.0;
`laravel/doctor` and `laravel/vet` are first-party and real.

## Phase 0 — qualify before starting

Do not run SSD for:

- one aggregate, 3-8 fields, CRUD + restore, no invariants → **`npm run module:make`** with a JSON spec
- a change to an existing module with a clear scope → read the module's `ARCHITECTURE.md`, then code
- a bug fix → fix it, and add the test that was missing

**And declare the lifespan first** — `PRINCIPLES/SKILL.md` §6. A one-shot artifact (a maintenance
command, a migration, a seeder) is not a module and does not get a module's ceremony; if that is what
was asked for, SSD is already too much and `module-make` will refuse with the same guidance. Getting
this wrong in either direction is expensive: a one-liner that arrives with a Data class, a repository
and a policy suite is a speculative build nobody asked for, and a real module that arrives without
them is a debt invoice dated today.

Escalating _out_ of SSD is allowed. The mode is declared in the module's `ARCHITECTURE.md`, and a
module that was specced as PROGRESSIVE and turned out to be CRUD should be demoted, not layered.

## Artifacts

Write artifacts to `specs/{feature-id}/`:

```
specs/{feature-id}/
├── spec.md
├── research.md
├── plan.md
└── tasks.md
```

`specs/` is also the inbox for `npm run module:make` — one `{name}.spec.json` per simple CRUD module.
Files beginning with `_` or `.` are skipped, so `_example.spec.json` is a reference, not a module.

## Definition of done

Not "the code works". All of:

1. Every user story in `spec.md` has code **and** a test.
2. Every FR is either implemented or explicitly dropped with a reason in `plan.md`.
3. The module's `ARCHITECTURE.md` matches the tree on disk.
4. `npm run module:done` exits 0 (and `module:codegen` if a `Data` class, enum, model or controller
   changed).
5. The graph is re-indexed if a symbol was added, renamed or deleted.
