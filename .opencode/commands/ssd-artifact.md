---
description: Runs Spec-Driven Development for a new backend module, service or API — 7 phases, each producing a file the next consumes, with mandatory real-time research before the technical plan. Not for simple CRUD, which has a generator.
---

# SSD ARTIFACT

The flow, its gates and its templates are defined in `.opencode/skills/SSD/SKILL.md`. **Read that
file first** — it is the source of truth. This command only states when to use it and the shape of
the output.

## When to run it

Use for a new module, a new service or a new API where the scope is **not** already agreed.

Do **not** use it for:

- simple FLAT CRUD → `npm run module:make` (see `/backend-new-crud`)
- a change with a clear scope inside an existing module → read that module's `ARCHITECTURE.md`, then code
- a bug fix → fix it, and add the test that was missing

Escalating out of SSD mid-flow is allowed and often correct: a module specced as PROGRESSIVE that
turns out to be CRUD should be demoted, not layered.

## The 7 phases

| Phase         | Produces                  | Gate                                                      |
| ------------- | ------------------------- | --------------------------------------------------------- |
| 1 Specify     | `spec.md`                 | every story has acceptance criteria; out-of-scope written |
| 2 Clarify     | edits `spec.md`           | zero `[NEEDS CLARIFICATION]` left unowned                 |
| 3 Plan        | `research.md` + `plan.md` | every version cited from real research                    |
| 4 Tasks       | `tasks.md`                | every FR and story maps to ≥1 task                        |
| 5 Analyze     | verdict                   | the plan still satisfies the spec — **before** code       |
| 6 Implement   | the code                  | `npm run module:done` exits 0                             |
| 7 Consolidate | updated docs              | `ARCHITECTURE.md` matches disk; traceability holds        |

Research lives inside Phase 3 as a mandatory step. Use **Tavily** for release state (`time_range:
"month"` when the question implies _now_) and **Context7** for the installed major's API. A version
string written from memory is the most common way this flow produces plausible, wrong code — and this
repo has concrete examples: PrimeVue 5 _deprecates_ rather than breaks and context7's PrimeVue docs
are v4, Vue 3.6 is still an RC, TypeScript 6.0 is a bridge to the native 7.0.

## Golden rule

**Never skip from spec straight to code.** If the user asks for one phase only, still use that phase's
format and say which earlier phases you assumed or are missing.

## Output

5 lines per phase: the artifact written, the gate that passed, the open questions, and what the next
phase needs from the human. At the end, the traceability table: every user story → code file → test.
