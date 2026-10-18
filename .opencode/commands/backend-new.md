---
description: Generates a PROGRESSIVE (layered) Laravel 13 module for domains with invariants, multi-step flows or cross-module orchestration. Read the architecture skill and confirm the escalation triggers first — most modules do not qualify.
---

# BACKEND NEW — intermediate / PROGRESSIVE module

## 1. Prove the escalation before writing anything

`SKILL-ARCHITECTURE.md` §4: FLAT → PROGRESSIVE requires **≥2** of 4 triggers. State which ones, with
evidence. If fewer than 2 fire, this is `/backend-new-crud` — say so and stop.

Typical qualifying combinations: ≥3 models with real business rules; an invariant that cannot be
violated; tests that must substitute persistence; a roadmap to extract the module.

Never promote "because enterprise". A 4-layer CRUD where nobody can name the load-bearing part is
worse than a flat one.

Before you commit, read `.opencode/skills/PRINCIPLES/SKILL.md` §3 — **PROGRESSIVE is an OCP bet**,
and Uncle Bob himself called the OCP "a big lie … that depended upon prescience and perfect
foresight". That is not an argument against layering; it is an argument for the de-escalation rule
being real, and for writing down the date the bet is reviewed.

## 2. Read, in this order

1. `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md` — **fully**: §2 boundary matrix, §3 invariant
   registry, §4 triggers, §5 optionality, §6 CQRS
2. `.opencode/skills/backend-php/SKILL.md` — framework and Spatie patterns
3. `stubs/architecture/ARCHITECTURE.PROGRESSIVE.md` — copy, do not invent the format
4. `.opencode/skills/owasp/SKILL.md` + `STACK.md`

## 3. Check the current stack before designing against it

```bash
composer show <package>        # what is INSTALLED
```

Then research, never recall: **Tavily** (`time_range: "month"`) for release state, **Context7** for the
installed major's API. A version written from memory is the most common way this command produces
plausible, wrong code.

## 4. Lay out the layer boundaries, not the file list

For each row of the boundary matrix (§2): which classes sit in it, and which row of the invariant
registry (§3) enforces it. If a layer has no test, it does not get created — that is the no-lock rule.

Optional folders (`Domain/Repositories/`, `Application/Queries/`, `Domain/ValueObjects/`, …) each need
their own trigger from §5. Write the trigger in `ARCHITECTURE.md` or do not create the folder.
`tests/Architecture/DesignSmellsTest.php` fails a module that declares a class nothing references,
or that ships a `Contracts/`, `Interfaces/`, `Abstracts/`, `Base/`, `Concerns/` or `Traits/` folder —
a generic interface folder is speculative by construction.

Two questions before you add anything, both from `PRINCIPLES/SKILL.md`:

- **Rule of Three.** Two real cases are a coincidence; extract on the third. And the test is whether
  the abstraction carries **knowledge** (a rule) or just shares a **shape** (a coincidence).
- **Small is not the goal.** A big module with a small interface beats three small modules with three
  interfaces. A wrapper that only forwards its own arguments is a failing test.

## 5. Build in the order the invariants demand

Domain → Application → Infrastructure → Http → Routes → Provider. The dependency arrow points inward
(§2), so an Infrastructure class that does not compile against a finished port means the port is
wrong, not the order.

## 6. Test to the invariant, not to the class

- Every module: 401 / 403 / 422 + happy path
- Every PROGRESSIVE module: ≥1 DB-free handler test with a mocked port — that test is what makes
  `Domain/` a layer instead of a folder
- Per the no-lock rule: if deleting a layer breaks no test, delete the layer

## 7. Close

```bash
npm run module:codegen
```

Report 5 lines: the triggers that fired, the layers created and the file enforcing each, the test
coverage, the accepted debt, and the gate result.
