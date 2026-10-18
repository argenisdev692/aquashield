# src/Shared — cross-cutting code with no owner module

Shared holds what **belongs to no module** and **knows no business rule**:

> If a class knows what a _booking_ is, it does NOT go here. It goes in its module.

Full rules → `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md` §5.

## Layout (layered, mirrors a module)

| Folder            | Holds                                                                                                                 |
| ----------------- | --------------------------------------------------------------------------------------------------------------------- |
| `Domain/`         | Ports (`StoragePort`, `ExportPort`, `AuditPort`, …), shared exceptions, transversal enums (`Currency`, `BillingUnit`) |
| `Application/`    | Reusable DTOs (`BulkUuidsData`, `*FilterData`), shared queries                                                        |
| `Infrastructure/` | Adapters: AI, Mail, Storage (R2), Export, Speech, Research, Resilience, `Http/Middleware`, `Console`, `OpenApi`       |
| `Providers/`      | `SharedServiceProvider` — the ONLY place Shared ports bind                                                            |
| `Support/`        | Pure helpers (`PhoneFormatter`, `EuSchengenCountries`)                                                                |

## Wiring (do not improvise — this order is load-bearing)

1. Ports bind ONLY in `SharedServiceProvider` (`bootstrap/providers.php`).
2. `SecurityHeaders` is prepended ahead of Inertia (`bootstrap/app.php`):
   Vite nonce sync + CSP/OWASP headers. Never duplicate the sync elsewhere.
3. The CSP nonce has ONE source: spatie/laravel-csp's `csp-nonce` binding.
   Never re-bind it — a shadow splits header nonce from view nonce.
4. Switchable adapters (mail, speech) read a config key, never a code edit.

## What does NOT go here

Module-owned adapters. While a single module uses it, an adapter lives in
`Modules/{Module}/Infrastructure/Services/`. When a SECOND module needs the
same adapter, **then** it is extracted here. An adapter with no consumer is
dead code — the catalogue above records where each one goes when the need
arrives.
