# CompanyData — ARCHITECTURE.md (PROGRESSIVE variant, only with ≥2 triggers)

> Copy this file to `src/Modules/{Module}/ARCHITECTURE.md` and fill in the
> brackets. Full rules → `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`.
> The test `tests/Architecture/ModuleComplexityTest.php` parses the `mode:` line
> and verifies `Domain/` and `Application/` purity.

mode: PROGRESSIVE

## Why PROGRESSIVE (≥2 triggers met — tick them)

- [x] ≥3 models with business rules: CompanyData model (40+ fields) + CompanyProfile service + Business rules around company validation and relationships
- [x] Invariants: Company data is a central invariant for the application — every request needs company branding, contact info, banking info for invoicing
- [ ] Tests that swap out persistence/external: Tests with mocked StoragePort for R2 uploads
- [ ] Extraction roadmap: Planned separation from the application core as company management grows

> **Audit note (2026-10-28):** only one Eloquent model and no state-machine invariant exist,
> so the ≥2 trigger threshold is not honestly met. Demotion to FLAT is proposed in the audit;
> the mode is left unchanged until the owner decides (a mode change is not an auto-fix).

## Boundaries and public contracts

- Public contract: public `GET /api/company-data` shaped by
  `Application\Dtos\CompanyBrandingDto` (Scramble-documented, no auth), plus the
  session `GET/PUT /company-data` shaped by `Application\Dtos\CompanyDataDto`
  (no `id`, `user_id` or `signature_path`).
- Port used internally: `Application\Repositories\CompanyRepository`, bound to
  `EloquentCompanyRepository` by the module provider.
- Events it emits: none yet — the singleton has no consumer event.
- What is NEVER exposed: Eloquent models, `user_id`, raw `id`, `signature_path`,
  bank/tax fields on the public endpoint.

## Applicable invariants (see skill §3)

1. `Domain/` imports no framework, ORM, HTTP, or infrastructure.
2. `Application/` imports only domain + abstractions + `Illuminate\Contracts\*`
   (interfaces). No Eloquent, no facades, no HTTP.
3. Controllers: validate → invoke ONE use case → map the response.
4. Repos live in `Infrastructure/Persistence/`: `CompanyRepository` interface + `EloquentCompanyRepository`.
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

- Bet made on: 2026-10-28
- Review due: 2027-04-28 — two quarters from the bet date
- What would prove this was over-engineering: no second consumer of
  `CompanyRepository` and no invariant beyond the singleton by 2027-04-28.

## Accepted debt and de-escalation condition

`CompanyRepository` still speaks in Eloquent models (`current()`/`save()`), so the
Application port depends on `Infrastructure/Persistence/CompanyData`. That is the
one real boundary debt in this module. It is tolerated only while the singleton
stays this small; demote to FLAT when 0/4 triggers hold for two quarters and no
test-covered invariants remain. Demoting means moving the files back to flat —
not deleting logic.

## Tests holding the layers up (no-lock rule)

- `Tests/Feature/CompanyDataEndpointTest.php` — 401/403/422, singleton upsert,
  response allowlist and `CompanyProfile` cache flush.
- `Tests/Unit/SaveCompanyDataActionTest.php` — DB-free handler test with a mocked
  `CompanyRepository`.
- `tests/Architecture/ModuleComplexityTest.php` — `arch()` enforces the layer
  boundaries; there is no `Domain/` in this module, so the no-lock rule applies
  to the Application port above.
