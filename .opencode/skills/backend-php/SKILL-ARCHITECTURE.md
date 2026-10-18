---
name: backend-architecture
description: Directory tree, layer boundaries and module layout for src/Modules and src/Shared. Covers the two complexity modes (FLAT and PROGRESSIVE), the escalation triggers, the verifiable invariant registry with its enforcement column, the backend boundary matrix, the optionality rules that prevent over-engineering in simple CRUD, and the rules for reading routes and closures.
---

# BACKEND ARCHITECTURE — directory tree, layers, complexity modes

> Read with `backend-php/SKILL.md` (language + framework rules) and `owasp/SKILL.md` (always on).
> Current state: `src/Modules/` is **empty** — everything below is a contract, not a description.
> See §10.

## 0. The one rule that matters

**Every module declares its mode in its own `ARCHITECTURE.md`.** The mode is never inferred from the
folder tree — a flat module and a layered module look identical until you know which one you are in.

```
src/Modules/Booking/ARCHITECTURE.md      # PROGRESSIVE
src/Modules/Users/ARCHITECTURE.md        # FLAT
```

Copy-paste variants (do not invent the format): `stubs/architecture/ARCHITECTURE.FLAT.md`
or `stubs/architecture/ARCHITECTURE.PROGRESSIVE.md`. The test parses the `modo:`
(or `mode:`) line — keep it on its own line.

Progressive complexity — one deployable, per-module layering. Neither all-simple nor all-hexagonal.
A mixed codebase is correct **only if the mix is declared**. Undeclared mixing is the failure mode,
not the mixing itself:

| Failure            | Symptom                                                                                                                  |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------ |
| **Under-declared** | Module looks flat, actually holds invariants. Someone "simplifies" it and breaks a rule three months later               |
| **Over-declared**  | Got 4 layers "because enterprise". Every CRUD change now touches 9 files and nobody can say which parts are load-bearing |

---

## 1. The two modes

### FLAT — the default

```
src/Modules/{Module}/
├── Models/               # Eloquent, HasUuids, $fillable, LogsActivity
├── Data/                 # Spatie Data — {Entity}Data + {Entity}FilterData. THE TS SOURCE.
├── Http/{Controllers,Requests}/
├── Actions/              # only when there is real logic to extract
├── Enums/
├── Events/
├── Jobs/
├── Exports/
├── Routes/{web,api}.php
├── ARCHITECTURE.md
└── Tests/
```

`Data/` is not optional and not decoration: it is what `typescript:transform` reads to generate the
frontend types, and what Scramble reads to document the API. Omit it and both break silently.

Deliberately **absent** from FLAT: `Domain/`, `Application/`, `Infrastructure/`, `Repositories/`.
Also absent, because this project does not use them: `Views/` (Inertia renders pages, there are no Blade
module views except exports) and `Policies/` — authorisation is Spatie `permission:*` middleware plus
`Gate::authorize()`; a per-module policy file is only correct when the module owns a tenancy/ownership
rule that a permission string cannot express (see `owasp/STACK.md` §11).

### PROGRESSIVE — only when ≥2 triggers are satisfied

```
src/Modules/{Module}/
├── Domain/                          # pure. No Eloquent, no Http, no framework.
│   ├── Models/                      # plain classes, no traits
│   ├── ValueObjects/                # Money, SlotRange, {Entity}Code
│   ├── Enums/
│   ├── Events/                      # domain events
│   ├── Exceptions/
│   ├── Policies/                    # domain rules — NOT Gate policies
│   └── Services/                    # pure calculation
├── Application/                     # use cases
│   ├── Actions/                     # one per operation
│   ├── Queries/                     # read side (see §5)
│   └── Dtos/
├── Infrastructure/
│   ├── Persistence/Eloquent/        # models + repository impls
│   ├── Migrations/
│   └── Services/                    # third-party SDKs, email, payments
├── Http/{Controllers,Requests}/
├── Data/                            # Spatie Data (still the response contract)
├── Routes/{web,api}.php
├── ARCHITECTURE.md
└── Tests/
```

---

## 2. The boundary matrix

The dependency rule stated as a table, because a table is what `arch()` can be pointed at.

| Layer             | May import                                                              | Must NOT import                                                        |
| ----------------- | ----------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| `Domain/`         | own entities/VOs/events, `Shared\Domain\*`, native PHP                  | framework, ORM, HTTP, `Infrastructure/`, `Application/`, `Http/`       |
| `Application/`    | `Domain/`, own abstractions, `Illuminate\Contracts\*` (interfaces only) | ORM, Eloquent, facades, HTTP objects, `Infrastructure/`, other modules |
| `Infrastructure/` | `Application/`, `Domain/`, `Shared/`, third-party SDKs                  | —                                                                      |
| `Http/`           | `Application/`, `Domain/`, `Data/`, framework HTTP                      | `Infrastructure/` implementations directly, ORM in the action body     |
| `Routes/`         | `Http/`, framework routing                                              | anything else                                                          |
| cross-module      | `Shared\Domain\Ports`, `Shared\Application\DTOs`, public `Data`         | `Modules\Other\Domain\*` and `Modules\Other\Application\*`             |

The last two rows are the bounded-context rule: **one module = one context.** A module never reaches
into another module's `Domain/` or `Application/`. If it needs something from another context, that
something is a port in `Shared\Domain\Ports`, a DTO in `Shared\Application\DTOs`, or a public `Data`
class. There is no third option, and "just this once" is how it starts.

### The one declared exception to Domain purity

`Shared\Domain\Ports` names `Illuminate\Http\Response` and `Symfony\...\StreamedResponse` **in return
positions** — `ExportPort::tabular()` and `::pdf()`, `WordExportPort`. A port whose entire job is
"stream this download" cannot describe that job without naming the response type, and the alternative
— pushing the streaming back into every module's controller — is the exact DRY failure the port
exists to prevent.

So: the ORM, facades and the framework core are banned from `Shared\Domain` entirely (enforced by
`arch()`), and `Illuminate\Http` / `Symfony` are permitted in `Ports` and nowhere else. The review
item that `arch()` cannot see: a port that reaches _past_ its return type — injecting a `Request`, a
Facade or a query builder into `Shared\Domain\Ports` is a real violation.

---

## 3. ARCHITECTURAL INVARIANTS

Verifiable rules. "Follow hexagonal and SOLID" is not an invariant — a comment is not a boundary.

| #   | Invariant                                                                                                                                                                                                                                                                                                      | Enforcement                                        |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------- |
| 1   | **Domain MUST NOT import** framework, ORM, HTTP, infrastructure, `Application/`, `Http/`. Only own VOs/entities/events, `Shared\Domain\*`, native PHP.                                                                                                                                                         | `arch()` — `ModuleComplexityTest`                  |
| 2   | **Application MAY import** domain, application abstractions, `Illuminate\Contracts\*` (interfaces only). MUST NOT access the ORM directly, HTTP objects, facades, or `new` an infrastructure implementation.                                                                                                   | `arch()` — `ModuleComplexityTest`                  |
| 3   | **Controllers MUST** validate transport input, invoke one use case (PROGRESSIVE) or hold one scoped query (FLAT), and map the response. No business logic.                                                                                                                                                     | review                                             |
| 4   | **Repository implementations MUST** live in `Infrastructure/Persistence/` and implement a `Domain`/`Application` port with a _different_ name (`SlotRepository` → `EloquentSlotRepository`). A contract returning Eloquent models gained nothing.                                                              | review                                             |
| 5   | **Use cases MUST NOT** access the ORM, HTTP objects, or `new` an adapter — they take ports resolved by the module provider.                                                                                                                                                                                    | `arch()` (the `new` half is review)                |
| 6   | **Every dependency points inward**: `Infrastructure/` → `Application/` → `Domain/`.                                                                                                                                                                                                                            | `arch()` — `ModuleComplexityTest`                  |
| 7   | **No module reaches another module's `Domain/` or `Application/`** — ports, DTOs or public `Data` only.                                                                                                                                                                                                        | `arch()` — `ModuleComplexityTest`                  |
| 8   | **Existing violations MUST be fixed**, not copied into new code.                                                                                                                                                                                                                                               | review                                             |
| 9   | **A layer with no test that fails if it moves is a folder, not a layer.**                                                                                                                                                                                                                                      | review + the escalation test                       |
| 10  | **KISS / YAGNI / DRY**: every optional folder needs its own trigger (§4). No command bus, mediator or event without a trigger.                                                                                                                                                                                 | `arch()` (no `Resources/`) + review                |
| 11  | **Clean / DDD**: ubiquitous language in names, explicit domain methods over generic setters, value-based VO equality, domain exceptions with business intent. If a reviewer cannot name the entry point, the invariants and the adapter boundary in under a minute, the module needs simplification.           | review                                             |
| 12  | **Aggregates**: ONE aggregate = ONE transactional boundary. Cross-aggregate consistency goes through domain events + `DB::afterCommit()` / outbox — never a shared transaction across two roots.                                                                                                               | review                                             |
| 13  | **Events**: immutable, fired AFTER persistence succeeds — implement `ShouldDispatchAfterCommit` (or `after_commit=true` on the connection) so a rollback can never publish. The listener lives in the CONSUMING module and is wired in ITS provider. No event for a direct one-step flow a method call covers. | review                                             |
| 14  | **Response shapes**: Spatie `Data` only — no `Resources/` folder, no `JsonResource` subclass. The sole exception is a public third-party API served by first-party `JsonApiResource`, which then gets its own controller (§6).                                                                                 | `arch()` + the `Resources/` test                   |
| 15  | **Tests**: every module ships Feature HTTP tests covering **401, 403 and 422** plus the happy path; every PROGRESSIVE module ships at least one DB-free handler test with a mocked port. Untested layers get demoted, not debated.                                                                             | review (the generator emits the 401/403/422 cases) |

Rows marked `review` are conventions. That is allowed — but it must be visible, which is why this is a
table and not a bulleted list. Anything that can be made testable, gets a test; anything that cannot
gets marked so nobody assumes a machine is checking it.

### Transport, not business state

Invariant 3 does **not** forbid one route serving two transports. In this stack a single route
legitimately answers an Inertia page visit and a plain JSON data request (what Pinia Colada calls),
selected by request format. That is one route, one serialization contract, two representations.

What is banned:

- two _serialization contracts_ on one endpoint (`Data` on one branch, `JsonApiResource` on the other)
- branching on **business state** inside a controller to pick behaviour
- a second query, a second mutation, or a business decision in the controller

---

## 4. Escalation triggers

Promote FLAT → PROGRESSIVE when **≥2** of these are true:

1. **≥3 models with business rules** (not just columns)
2. **Invariants** — a state that cannot be violated (state machine, lifecycle, precondition)
3. **Tests need to substitute persistence or an external service**
4. **Roadmap says it will be extracted** into a microservice

For this project specifically:

| Domain                               | Verdict           | Why                                                                                                        |
| ------------------------------------ | ----------------- | ---------------------------------------------------------------------------------------------------------- |
| Reservas / calendario                | **CQRS first** ✅ | Read and write profiles differ — a month of slots vs one transactional booking                             |
| `laravel/ai`                         | **DDD later** ⚠️  | Conversation + memory + tools is genuinely complex, but starts with one model. Escalate when state appears |
| Fortify / passkeys / 2FA / OTP       | ❌ never          | The domain belongs to Fortify. DDD here is ceremony                                                        |
| `laravel-permission`                 | ❌ never          | Spatie already owns that domain                                                                            |
| `activitylog`                        | ❌ never          | Spatie already audits                                                                                      |
| medialibrary / simple-excel / dompdf | ❌ never          | Adapters, not domain                                                                                       |

### De-escalation

Writing only the "when to promote" rule produces irreversible debt. The inverse:

> Demote PROGRESSIVE → FLAT when the module has satisfied **0/4 triggers for two consecutive
> quarters** and its `Domain/` has no invariants covered by tests. Demoting means moving the files
> back to flat — not deleting logic.

**Why that rule exists, and what PROGRESSIVE actually is.** Uncle Bob, in _CleanCoders_ episode 10,
tears down his own Open/Closed Principle: the promise is "in fact a big lie… an illusion that
depended upon prescience and perfect foresight." Declaring `mode: PROGRESSIVE` _is_ an OCP bet — an
extensibility point bought with Fowler's two costs (build, and delay even when the prediction is
right). The de-escalation rule is the discipline that makes the bet safe, and it is the part almost
nobody writes down. A bet with a scheduled review is a bet; a bet with only a trigger is a trap.

OCP is also the only one of the five SOLID principles that costs you up front — SRP, LSP, ISP and DIP
are close to free. **When a change is expensive and you cannot yet prove it is needed, that is the
argument for FLAT.** Full treatment: `PRINCIPLES/SKILL.md` §3.

### The no-lock rule

> A `Domain/` with **no test that fails if someone moves it** is not a layer. It is a folder.
> Layers without a test enforcing them are comment-only structure.

---

## 5. The optionality rules (anti-over-engineering)

**The default is to skip every one of these.** Create each only when its own trigger fires.

| Thing                           | Create when                                                                                | Not                                                        |
| ------------------------------- | ------------------------------------------------------------------------------------------ | ---------------------------------------------------------- |
| `Domain/Models/`                | ≥2 triggers, or invariants need a type that Eloquent cannot express                        | because "we use DDD"                                       |
| `Domain/ValueObjects/`          | a value is passed across a boundary and has meaning beyond its type (`Money`, `SlotRange`) | to wrap a single `string`                                  |
| `Domain/Repositories/`          | **≥2 real implementations**, or a test genuinely needs a DB-free double                    | one Eloquent implementation — that is boilerplate, not DIP |
| `Application/Queries/`          | a read is expensive, or its contract deserves a name                                       | mirroring every trivial query                              |
| `Application/Dtos/`             | data crosses a boundary where the shape matters                                            | re-wrapping an Eloquent model                              |
| `Domain/Services/`              | logic has no natural home and is pure calculation                                          | a dumping ground                                           |
| `Tests/Unit/`                   | there is pure logic worth testing without the framework                                    | mirroring every test                                       |
| `Infrastructure/` (whole layer) | 2 of the 4 triggers                                                                        | a folder holding one adapter                               |

**DIP is measurable.** A port with exactly one implementation _and_ no test that substitutes it is
boilerplate that costs a reader two files to understand one query. That is a judgement call by
definition, so it is made in review, explicitly — not silently inherited from a template.

**Repository naming:** the interface is `SlotRepository` (in `Domain/Repositories/`), the
implementation is `EloquentSlotRepository` (in `Infrastructure/Persistence/Eloquent/`). Never the
same name twice — it forces an explicit `use` everywhere and hides which is which.

**If the interface returns Eloquent models, you gained nothing** — you only moved the `use`
statement. A repository contract is only worth having when it speaks in domain terms.

**Never create `Contracts/` "just in case."** An interface with no second implementor and no test
that substitutes it is dead code. Neither is `Interfaces/`, `Abstracts/`, `Base/`, `Concerns/` or
`Traits/` in a FLAT module — a generic folder is speculative by construction. A port belongs in
`Domain/Repositories/` or `Application/`, **named for the thing it abstracts**, and only with ≥2
implementations or a test that substitutes it. `tests/Architecture/DesignSmellsTest.php` fails all
of these by folder name, and fails any class a module declares that nothing else references.

**Rule of Three.** Two occurrences are a coincidence; the third is the pattern. Write the second copy
when the second case is real, and extract on the third — not on the second, which is YAGNI wearing a
DRY costume, and not after the fifth, which is the debt Fowler does want paid. And ask _what knowledge
the abstraction carries_: two copies of a **rule** is a DRY fail; two copies of a **shape** with no
shared rule is not duplication of anything. Full treatment: `PRINCIPLES/SKILL.md` §1.

**Small is not the goal.** Ousterhout's test is whether the interface is much simpler than the
implementation, not how few lines the module has. A big module with a small interface beats three
small modules with three interfaces, and a method whose entire body forwards its own arguments is
both a dumb pass-through (Henney) and a shallow module (Ousterhout). That last one is now a failing
test. Note the inverse case: a wrapper that _transforms_ an argument is doing work and is correct —
`R2StorageAdapter::delete()` normalising the key is not a smell.

**Duplication that is allowed.** Naming the exceptions is what stops an absolute rule from being
applied where it hurts. Accept: two identical controller actions in two modules (they will diverge);
test setup that repeats production's shape (independence is the point); two composables with the same
shape and different endpoints; test code mirroring production structure. Never acceptable: a second
definition of a business rule, a permission name, an owner scope or a filter. Full table:
`PRINCIPLES/SKILL.md` §8.

**Separate only along a boundary you can name.** "It belongs in another layer", "it might change
independently" and "it's cleaner over there" are not boundaries. If you cannot name it, leave it
local — locality of behavior beats separation when the boundary is imaginary, and an agent that
splits a coherent unit across nine files loses the ability to see it.
`PRINCIPLES/SKILL.md` §7.

---

## 6. CQRS — read side only, and only per module

**Laravel has no first-party CQRS.** There is no mediator, no command bus, no read model. Most projects
that "need CQRS" need a read _query class_, not a distributed write side.

```php
namespace Modules\Booking\Application\Queries;

final class AvailabilityQuery
{
    // Eloquent models stay inside. The caller gets a DTO or a spatie/laravel-data object.
    public function forDay(Carbon $day, int $serviceId): Collection { /* ... */ }
}
```

That single class buys the real value — a named, testable contract, isolation for caching, and a place
to optimise the expensive read — with zero ceremony.

Full CQRS (separate read store, denormalised read model, synchronisation) is only warranted when the
read model **cannot** be computed on demand. For a calendar, an index usually suffices.

**Never introduce a command bus or mediator without a trigger.** If a module has three actions, three
plain classes called from a controller is the correct amount of ceremony.

---

## 7. `src/Shared/` — layered catalogue (this project's actual layout)

`Shared\` holds things with **no business logic** and **no owner module**. If a class knows what a
booking is, it belongs in a module, not here. The layout mirrors a module:

```
src/Shared/
├── Domain/            # ports (StoragePort, ExportPort, AuditPort, …), shared
│                      # exceptions, transversal enums (Currency, BillingUnit)
├── Application/       # reusable DTOs (BulkUuidsData, *FilterData), queries
├── Infrastructure/    # adapters: AI, Mail, Storage(R2), Export, Speech,
│                      # Research, Resilience, Http/Middleware, Console, OpenApi,
│                      # Branding, Company, Docs, Audit, Persistence, Support
├── Providers/         # SharedServiceProvider — the ONLY place ports bind
└── Support/           # pure helpers (PhoneFormatter, EuSchengenCountries)
```

Rules:

- **One owner for wiring.** Ports bind ONLY in `SharedServiceProvider`
  (registered in `bootstrap/providers.php`). No module binds a Shared port.
- **Binding lifetime follows the state, not the habit.** `scoped` when the instance must live for ONE
  request/job — the container flushes it when a new lifecycle starts (Octane request, queue worker
  job). `singleton` ONLY when the same instance is genuinely wanted for the whole application/worker
  lifetime; a singleton that holds request or job state leaks that state across lifecycles. Official:
  Laravel 13 `container.md`, "Binding Scoped Singletons" (`scoped()` and the `#[Scoped]` attribute).
- **One source for the CSP nonce.** Spatie's `csp-nonce` binding. Never
  re-bind it in Shared — a shadow binding splits the header nonce from the
  view nonce (verified failure mode, 2026-09-27).
- **Middleware order.** `SecurityHeaders` is prepended ahead of Inertia in
  `bootstrap/app.php`: it syncs the Vite nonce + emits CSP/OWASP headers.
  Do not duplicate that sync in module/app middleware.
- **Config switches, not code changes.** Mail (`mail.adapter`), speech
  (`services.speech.synthesizer`) pick adapters via env. New switchable
  adapter = new config key + provider `match`, documented the same day.
- New adapters still wait for their first real consumer — **an adapter with
  no consumer is dead code.** The catalogue above is the record of where each
  one goes when the need arrives, not a licence to pre-build the next one.

---

## 8. Composer autoload

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Modules\\": "src/Modules/",
        "Shared\\": "src/Shared/"
    }
}
```

`src/volt/` is intentionally **not** mapped — it holds `.vue` files resolved by the Vite `@` alias,
not PHP.

Run `composer dump-autoload` after adding a new root.

---

## 9. Module ServiceProvider

Laravel 11+ auto-discovers **only** `app/Providers/`. A module provider must be registered by hand:

```php
namespace Modules\Booking\Providers;

class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SlotRepository::class, EloquentSlotRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
```

```php
// bootstrap/providers.php
Modules\Booking\Providers\BookingServiceProvider::class,
```

Only add a provider when the module actually has routes, migrations or bindings to declare. A FLAT
module whose routes live in `routes/web.php` needs none.

Note the migration path: a module with its own `Infrastructure/Migrations/` is PROGRESSIVE. A FLAT
module's migration goes in the project's `database/migrations/` — which is what `module-make` writes.

---

## 10. Reading this repo: what exists and what does not

This section exists because a skill that describes a tree can be read as a description of a codebase.

| Path                      | State                                                                                                                                                                                                                                                                                                                     |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `src/Modules/`            | **empty** — `.gitkeep` + `README.md`. No domain module is implemented.                                                                                                                                                                                                                                                    |
| `tests/Architecture/`     | three suites — `ModuleComplexityTest.php` (declared modes, layer boundaries, global hygiene), `DesignSmellsTest.php` (dumb pass-throughs, orphan module code), `FrontendBoundariesTest.php` (frontend import/token/no-`any` rules). Module- and page-specific assertions **skip themselves** while their trees are empty. |
| `resources/js/generated/` | `.gitkeep` only — `typescript:transform` has no `Data` class to read yet.                                                                                                                                                                                                                                                 |
| `stubs/architecture/`     | both variants present, ready to copy                                                                                                                                                                                                                                                                                      |
| `src/Shared/`             | populated — ports, adapters, DTOs, `SharedServiceProvider`                                                                                                                                                                                                                                                                |
| `app/`                    | the framework shell: Fortify actions, `User`, Inertia middleware, providers                                                                                                                                                                                                                                               |

Consequences you must respect:

- **The invariant registry in §3 is currently vacuous.** Nothing is enforcing anything yet, because
  there is no module to enforce it on. It becomes real the moment the first module lands — which is
  why the first module must be generated by `module-make` and keep its `ARCHITECTURE.md`.
- **Do not claim a module, screen, DTO or policy exists** because a rule above names it.
- The 401/403/422 coverage in invariant 15 is emitted by `scripts/module-make.mjs`. Writing a module
  by hand means writing those three cases by hand.
- `phpstan.neon` analyses `app`, `src/Shared` and `src/Modules`; with `src/Modules` empty it is
  analysing two of three. Level 5 is global; raising it per module is a manual edit, not an automatic
  ratchet — treat it as a deliberate, dated decision.

---

## 11. Non-negotiable conventions

- **UUIDv7 primary keys** on every model via `HasUuids`. `HasVersion7Uuids` was removed in Laravel 12.
- **Bidirectional relations** — if a FK exists, both `hasMany`/`belongsTo` sides are declared.
- **Response shapes are Spatie Data**, for Inertia props _and_ JSON. `JsonResource` is a FAIL.
- **Bulk delete + bulk restore ship together** whenever the UI exposes row selection. Never one without
  the other, never one without a test.
- **N+1 prevention is mandatory** — `with('rel:id,fk,col')` with explicit columns, never a bare `with()`.
- Dates in `Y-m-d` over the wire; timezone-aware Carbon in the domain; `float` never for money — use
  integer minor units or a `Money` value object.
- Routes: `->whereUuid('uuid')` on UUID params, `permission:*` middleware on every mutating verb.

---

## 12. Review heuristic

Before merging a module, ask:

1. Does `ARCHITECTURE.md` match what is on disk? (If not, the mode is a lie.)
2. Is every optional folder justified by its own trigger?
3. Would deleting a layer fail a test? (If not, it is a folder.)
4. Can I name the file that enforces each boundary? (If not, the boundary row in §3 says `review`.)
