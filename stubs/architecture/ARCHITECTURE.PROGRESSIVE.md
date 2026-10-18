# {Module} — ARCHITECTURE.md (PROGRESSIVE variant, only with ≥2 triggers)

> Copy this file to `src/Modules/{Module}/ARCHITECTURE.md` and fill in the
> brackets. Full rules → `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`.
> The test `tests/Architecture/ModuleComplexityTest.php` parses the `mode:` line
> and verifies `Domain/` and `Application/` purity.

mode: PROGRESSIVE

## Why PROGRESSIVE (≥2 triggers met — tick them)

- [ ] ≥3 models with business rules: [...]
- [ ] Invariants: [...] (e.g. "a booking must never overlap")
- [ ] Tests that swap out persistence/external: [...]
- [ ] Extraction roadmap: [...]

## Boundaries and public contracts

- Port(s) other modules may use: [`SlotRepository`, ...]
- Events it emits: [`BookingCreated`, ...] — the consumer lives in ITS module.
- What is NEVER exposed: Eloquent models, internal `Domain/`.

## Applicable invariants (see skill §3)

1. `Domain/` imports no framework, ORM, HTTP, or infrastructure.
2. `Application/` imports only domain + abstractions + `Illuminate\Contracts\*`
   (interfaces). No Eloquent, no facades, no HTTP.
3. Controllers: validate → invoke ONE use case → map the response.
4. Repos live in `Infrastructure/`: `XRepository` interface + `EloquentXRepository`.
5. Every dependency points inward; existing violations get fixed, never copied.
6. No module reaches another module's `Domain/` or `Application/`.
7. Every class here has a consumer — `DesignSmellsTest.php` fails orphans and
   `Contracts/`-style folders.
8. No dumb pass-through: a method that only forwards its own arguments is a
   failing test (`PRINCIPLES/SKILL.md` §4).

## This is an OCP bet — name it and date it

Declaring PROGRESSIVE is an _extensibility point_: it costs construction **and**
delay (Fowler), the delay even when the trigger was right. Uncle Bob called the
Open/Closed Principle "a big lie … that depended upon prescience and perfect
foresight". The de-escalation rule below is what makes the bet safe.

- Bet made on: [date]
- Review due: [date] — two quarters from the above
- What would prove this was over-engineering: [e.g. "no second consumer of the port by <date>"]

## Accepted debt and de-escalation condition

[What is postponed. Demote to FLAT when 0/4 triggers hold for two quarters
and no test-covered invariants remain in `Domain/`.]

## Tests holding the layers up (no-lock rule)

- [File]:[test] that fails if `Domain/` imports Eloquent —
  [e.g. `Tests/Unit/SlotRangeTest.php`]
- [File]:[test] of a handler with a mocked repository (DB-free) —
  [e.g. `Tests/Unit/CreateBookingTest.php`]
