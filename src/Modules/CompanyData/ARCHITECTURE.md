# CompanyData — ARCHITECTURE.md (FLAT)

mode: FLAT

## Why FLAT

One Eloquent singleton (the company profile) plus four brand-asset uploads. No
state machine and no second consuming module. The singleton is an invariant
("exactly one row"), but it is one rule expressed by `CompanyData::current()`
and the single write path — not a reason to add layers. The PROGRESSIVE
declaration of 2026-10-28 never met the ≥2 trigger threshold; the module was
demoted on 2026-11-22 and the files moved — the logic was not deleted.

## Triggers evaluated (0-1 of 4 to stay FLAT)

- [ ] ≥3 models with business rules — one model, one row.
- [x] Invariants — exactly one company row. `CompanyData::current()` defines the
      read; the action updates that row in place and throws 404 when it is not
      provisioned. The `singleton` unique column is the database guard. One
      trigger, below the ≥2 threshold, so FLAT stands.
- [ ] Tests that need to swap out persistence or an external service — the
      asset path is tested through `Shared\Domain\Ports\StoragePort`, which is
      already the seam. The module's own port had one implementation.
- [ ] Roadmap: extraction to a microservice — no.

## Layout

- `Models/CompanyData.php` — the singleton; `CompanyData::current()` is the
  module's definition of "the row". `CompanyProfile` mirrors the active-oldest
  query in Shared so it can stay decoupled from this module (accepted
  duplication; see Accepted debt).
- `Data/` — Spatie Data response/request contracts (the TypeScript source).
- `Actions/` — `SaveCompanyDataAction`, `StoreCompanyAssetAction`,
  `DeleteCompanyAssetAction`.
- `Queries/CompanyAssetsQuery.php` — key → renderable URL, one fallback rule.
- `Enums/CompanyAsset.php`, `Support/R2CompanyAssetStorage.php`.
- `Http/`, `Routes/{web,api}.php`, `Providers/`, `Tests/`.

## Accepted debt

- `R2CompanyAssetStorage` reaches R2 through `Shared\Domain\Ports\StoragePort`.
  If a second adapter or a test double for the module's own asset contract is
  ever needed, introduce that port then — not before.
- The public `GET /api/company-data` surface is real (welcome page and external
  landing sites) and stays.
- The active-oldest rule exists twice on purpose: `CompanyData::current()` for
  the module and `CompanyProfile::firstCompanyRow()` for Shared branding, which
  must not depend on a module. Any change to the singleton rule must update both.

## What is never exposed

Eloquent models, `user_id`, the raw `id`, `signature_path`, and bank/tax fields
on the public endpoint.

## Tests

- `Tests/Feature/CompanyDataEndpointTest.php` — 401/403/404/422, update in
  place, response allowlist, financial-field audit and `CompanyProfile` cache
  flush.
- `Tests/Feature/CompanyAssetEndpointTest.php` — upload/clear through a fake
  `StoragePort`, private signature URL, and the asset-change activity entry.
- `Tests/Feature/PublicCompanyDataEndpointTest.php` — public branding and the
  no-row fallback.
- `Tests/Unit/SaveCompanyDataActionTest.php` — create-then-update in place.
- `tests/Architecture/ModuleComplexityTest.php` — a FLAT module ships no
  `Domain/`, `Application/` or `Infrastructure/` folder.
