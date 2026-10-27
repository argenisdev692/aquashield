---
name: owasp-stack
description: Stack bindings for the OWASP baseline — PHP 8.5 · Laravel 13 · Vue 3.5 · Inertia v3 · Spatie · R2. Load alongside owasp/SKILL.md on any backend/frontend task of THIS project. Not portable.
---

# OWASP Stack Bindings — this project (not portable)

> Companion to `owasp/SKILL.md` (the portable enterprise core). Everything here
> is stack-specific: package names, snippets, env keys, verified state. When the
> stack upgrades, update THIS file — the core stays untouched.
>
> **Stack (verified 2026-09-27):** PHP 8.5.9 · Laravel 13.33 · Vue 3.5.43 ·
> Inertia 3.7.1/3.4.0 · PrimeVue 5.0.1 + Volt · Tailwind 4.3.3 · Pinia 4.0.3 +
> Colada 1.4.6 · Spatie permission 8.x / data 4.x / activitylog 5.x · R2 storage.

---

## §1 — Backend: auth middleware + Spatie Permission 8.x

```php
// bootstrap/app.php
$middleware->alias([
    'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
    'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
    'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
]);
```

- Deny by default: every route guarded by `auth` unless explicitly public.
- `Route::middleware('permission:VIEW_USERS')` or Policies with `Gate::authorize()`.
- UUID params: `->whereUuid('uuid')` — never raw `id`.
- User model carries `HasRoles` (`->assignRole()`, `->can()`).
- Response/input shaping: Spatie `Data` DTOs with `#[MapOutputName(SnakeCaseMapper::class)]` (allowlist). Never `->toArray()` on a raw model.
- **Exception — Laravel 13 `JsonApiResource`**: for a _public, spec-compliant third-party API_, `Illuminate\Http\Resources\JsonApi\JsonApiResource` (`make:resource X --json-api`) is correct. A classic `JsonResource` for an endpoint the frontend consumes is a FAIL (bypasses `typescript:transform`).
- **Frontend**: `<PermissionGuard :permission="'VIEW_X'">`; authorization by **`permissions`**, never `roles`. UI hiding is courtesy, never control.

## §2 — Sessions, CSRF, hashing, throttle

- Web (primary) → Laravel sessions + Inertia. CSRF via `PreventRequestForgery` (Laravel 13 name; `VerifyCsrfToken` kept only as deprecated subclass) — token + `Sec-Fetch-Site` origin check.
- API (secondary, mobile/external) → Sanctum personal access tokens. **Opaque hashes, NOT JWT** — annotations must NOT claim `bearerFormat="JWT"`.
- Hashing: `argon2id` in `config/hashing.php` (deliberately published — skeleton default is bcrypt).
- `RateLimiter::for('login', …)` 5/min per IP+email (`FortifyServiceProvider`); `two-factor` + `passkeys` limiters too.
- Production session env: `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_COOKIE=__Host-…`.
- Login: `$request->session()->regenerate()`. Logout: `Auth::logout()` + `invalidate()` + `regenerateToken()`. Frontend logout: `router.visit('/login')` + `queryCache.clear()`.
- Idle timeout: sliding-expiration middleware (30 min default, `last_activity` in session).

## §3 — Validation, mass assignment, output

- Every input: `FormRequest` or Spatie `Data` with attributes (`#[Required]`, `#[Email]`, `#[Max]`, `#[Rule]`). Never `request()->all()` into `Model::create()`.
- Every model declares `$fillable` (or `#[Fillable]`). Never `$guarded = []`.
- Eloquent everywhere; `DB::raw()`/`whereRaw()` only with bindings.
- Shell: forbidden (use `Process` with arg arrays if ever unavoidable). No `file_get_contents($userInput)` — `Storage` API only.
- Vue auto-escapes `{{ }}`. `v-html` forbidden on user input (server sanitize first). No `eval()` / `new Function(string)`.
- Client validation: `@primevue/forms` + Zod v4 via `zodResolver` — backend DTO stays authoritative.

## §4 — Secrets, encryption, cookies

- `APP_URL=https://…` and `URL::forceScheme('https')` in production (`AppServiceProvider`); HSTS via SecurityHeaders (§5).
- `Crypt::encryptString()` for at-rest sensitive fields.
- Secrets via `.env` + placeholders in `.env.example` only; CI/prod from the platform secret store.
- Audit: Spatie `LogsActivity` (`Spatie\Activitylog\Models\Concerns\LogsActivity` + `Spatie\Activitylog\Support\LogOptions` — the v4 namespaces fatal) with explicit `logOnly([...])` + `logOnlyDirty()` + `dontLogEmptyChanges()`. Never `logAll()`, never secrets/PII.

## §5 — Headers, CSP, CORS, build

- `SecurityHeaders` middleware (`Shared\Infrastructure\Http\Middleware\`) is **prepended** ahead of Inertia in `bootstrap/app.php`: OWASP headers + nonce-based CSP + per-request Vite nonce sync. Single owner — never duplicate the sync.
- TLS-terminating proxies (Railway): `$middleware->trustProxies(at: '*')` in `bootstrap/app.php` makes `X-Forwarded-Proto: https` authoritative, so `@vite`/`asset()`/font preloads emit HTTPS URLs. Without it the page loads over HTTPS but references `http://` assets (mixed content + CSP `'self'` blocks them).
- CSP nonce: spatie/laravel-csp v3 exposes **only** the `@cspNonce` Blade directive and the `csp-nonce` container binding — there is **NO `csp_nonce()` helper**. Never re-bind `csp-nonce` (shadow splits header/view nonces). `style-src` uses the CSP3 split (`style-src-elem`/`style-src-attr`) because Volt overlays inject runtime styles; scoped relaxations exist ONLY for Scramble docs UI and Horizon/Telescope dashboards (gated pages).
- CORS (`config/cors.php`): explicit `allowed_origins` list, never `['*']` on authenticated endpoints; wildcard subdomains via `allowed_origins_patterns` regex.
- Single header/CSP emitter is the Shared middleware; `bepsvpt/secure-headers` was removed (it was never registered, and a second CSP would intersect with this one).
- Vite prod: minified, `build.sourcemap: false`, only `VITE_*` keys exposed — never secrets. **One documented exception:** `VITE_PRIMEUI_LICENSE` is NOT a secret (PrimeUI terms: no sensitive data, dev-only notice) — must NOT be reported as a leak.

## §6 — Supply chain

- `composer.lock` + `package-lock.json` committed. CI: `composer audit` + `npm audit --omit=dev` on every PR.
- Pin majors; changelog before upgrades; security patches ≤7 days.
- `laravel/vet`: `vet.json` committed (219 packages trusted). `composer update:safe` for interactive vendor review; `--minimum-release-age` as fresh-release defense (OWASP A03).
- No unknown-maintainer packages without verification.

## §8 — Uploads, signed URLs, webhooks

- Validate MIME + size + extension; re-encode images (`intervention/image`), MIME+`finfo` PDFs.
- Signed TTL by sensitivity: PII 5 min · standard private 15 min · heavy exports 30 min. `Storage::disk('r2')->temporaryUrl(…)` only — never raw bucket URLs.
- Webhooks: HMAC verify every callback.

## §9 — Logging & alerting

- Monolog `JsonFormatter`; OpenTelemetry where topology justifies.
- Spatie Activitylog on every mutating aggregate (§4 namespaces).
- Alerts: login bursts, permission escalations, 5xx spikes, R2 4xx, queue failures.

## §10 — Timeouts, retries, concurrency, client errors

- Every external call: `Http::timeout(5)->retry(2, 200)` (tune per surface; R2 uploads 30s). Retries on idempotent ops only.
- Shared writes: `DB::transaction()` + `lockForUpdate()` or optimistic versioning.
- Domain exceptions per module (`Domain/Exceptions/`), mapped to HTTP codes centrally.
- Frontend: Inertia `onHttpException`/`onNetworkError` wired in `app.ts`; action failures via `useToast({ severity: 'error', … })` with actionable text.

## §11–§13 — Object / property / function authz

- `findByUuid` → load → verify `tenant_id`/`user_id`/`owner_id` → 403 on mismatch. Policies colocated in the owning module; Spatie permissions for capabilities.
- Tests: every show/update/delete has a **401 (unauthenticated)**, **403 (authenticated, not the owner)** and **422 (invalid input)** case. `scripts/module-make.mjs` emits all three for a `userOwned` module — that is the generator's contract, verified 2026-09-28, and a module written by hand must write them by hand.
- A `userOwned: false` module has no owner to 403 against, so the generator emits 401 + 422 only. That is correct **only** when the entity is genuinely global; a shared-but-restricted entity needs a real policy and a hand-written 403.
- Admin routes under `permission:*` groups, separate route files for web vs api. Sidebar renders by permission from ONE source (`Shared` permission enum), 1:1 with backend.

## §14 — Throttles, pagination, queues, client discipline

- `login`/`register` 5/min · `auth`/`api` 60/min · `export` 10/min.
- Pagination mandatory: default 15, cap 100.
- Upload caps per use case (e.g. 5 MB invoices, 20 MB classroom material).
- Queues: `default` (fast) vs `heavy` (exports) with separate workers + `--max-time=120`.
- Client: `staleTime` 2 min on list queries; 300 ms debounce on search; disabled submit state (no double-submit).

## §15 — Outbound HTTP, R2

- Allowlist before any `Http::get($userUrl)`; DNS-validate private ranges (§1 list).
- Validate third-party responses (schema via Spatie Data + status + content-type).
- R2 only via `Storage::disk('r2')`; signed URLs only.

## §15.5 — Hardening snippets (Laravel 13, verified)

Session `.env` (production): `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`, `SESSION_EXPIRE_ON_CLOSE=false`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_DOMAIN=.example.com`, `SESSION_COOKIE=__Host-{app}_session`.
UUID binding: `Route::get('/users/{user:uuid}', …)->whereUuid('user')->middleware(['auth', 'permission:VIEW_USERS'])`.
Signed routes: `URL::signedRoute(...)` / `temporarySignedRoute(..., now()->addMinutes(15), …)` + `signed` middleware.
Throttles in `bootstrap/app.php` (`login` 5/min by email+ip, `api` 60/min).
IDOR: Policy `view()` checks tenant scope + `Gate::authorize('view', $order)` in controller.
Honeypot: `spatie/laravel-honeypot` `ProtectAgainstSpam` + hidden field in the form.
Double validation: Zod schema (`Modules/{ctx}/schemas/`) + mirrored Spatie `Data` (server wins).
Upload: validate → `store(..., ['disk' => 'r2'])` → re-encode → `temporaryUrl(..., +15 min)`.
OTP: `spatie/laravel-one-time-passwords` for confirmations/MFA.

## §16 — LLM gateway (laravel/ai v1)

One `AiClientInterface` implementation enforces: PII redaction, per-user rate limit, timeout, circuit breaker, audit entry. Never call a provider SDK from a controller/action. `laravel/ai` wraps Prism itself — `prism-php/prism` alongside it is banned (two bridges, one call). Pin model versions; treat output as untrusted (§16 core table applies unchanged).

## §17 — This project's verified state (read before claiming compliance)

### Green (verified 2026-09-27)

| Control                  | Evidence                                              |
| ------------------------ | ----------------------------------------------------- |
| Argon2id                 | `config/hashing.php` published deliberately           |
| CSRF                     | Inertia + `PreventRequestForgery` (Laravel 13 name)   |
| Login throttle 5/min     | `FortifyServiceProvider`                              |
| OTP + passkeys throttled | `RateLimiter::for('two-factor'                        | 'passkeys')` |
| Audit v5 namespaces      | `Models\Concerns\LogsActivity` + `Support\LogOptions` |
| CSP wired                | `config/csp.php`, `CSP_ENABLED` default true          |
| Upload validation        | `intervention/image` re-encode                        |
| Single LLM bridge        | `laravel/ai` v1 only                                  |

### Verified inside a sandbox that denies `.env`

`.claude/settings.json` denies `Read(.env)` and `Read(.env.*)`. That is correct hardening — but the
Red list below is mostly `.env` gaps, and this section must still be checkable. Use the framework as
the reader, never the raw file:

```bash
php artisan config:show session        # resolved session config
php artisan config:show database       # resolved DB config
php artisan about                      # env-derived summary
php artisan tinker --execute="dump(config('app.debug'), config('session.encrypt'), config('session.secure_cookie'))"
php artisan doctor                     # catches most of it without opening .env
```

`php artisan config:cache` makes `config:show` reflect the cached values, which is what production
actually runs — so a stale cache is a finding, not a workaround. Never ask the developer to paste
`.env` contents, and never read it through another tool to defeat the deny rule.

### Reading command output — `laravel/pao`

`laravel/pao` is a dev dependency that **replaces the output of `phpunit`, `pest`, `phpstan`, `rector`
and `artisan` with compact JSON when it detects an AI agent**, and leaves it untouched for humans. It
is first-party (MIT) and included by default in new Laravel apps.

Consequences for the gate:

- A green run is green — do not re-run a failing step hoping the output changes.
- `php artisan test` output is structured, not prose. Read the structured fields, not the formatting.
- A red run's reason is in the JSON. Quote it verbatim in your report; do not paraphrase a gate
  failure from memory.
- Because PAO silences the human-readable trace, **never use "it printed no error" as evidence of
  success** — use the exit code.

### Red — fix before production

| Gap                            | Where            | Fix                                                       |
| ------------------------------ | ---------------- | --------------------------------------------------------- |
| `SESSION_ENCRYPT=false`        | `.env`           | `true` (§15.5)                                            |
| `SESSION_SECURE_COOKIE` absent | `.env`           | `true` in production (no default in `config/session.php`) |
| `SESSION_DOMAIN` unset         | `.env`           | apex domain or deliberate host-only null                  |
| No source-map policy           | `vite.config.js` | `build: { sourcemap: false }` explicitly                  |
| Session keys missing           | `.env.example`   | add production session keys so the gap cannot recur       |

`APP_DEBUG=true` is correct for `local`, must be `false` in production.

### Known deviations from the portable core (documented, not hidden)

1. CSP emitter is the custom `SecurityHeaders` middleware, not spatie's `AddCspHeaders` (spatie/laravel-csp is installed for the `csp-nonce` binding/directive only; custom middleware owns nonce sync + scoped docs/monitoring policies). `bepsvpt/secure-headers` was removed to avoid a second, intersecting policy.
2. `VITE_PRIMEUI_LICENSE` in the bundle is NOT a leak (see §5).
3. `laravel/doctor` excludes `SqliteDatabaseExists` (Windows false positive, v0.1.0); `doctor --fix` must never run for it.
4. `.env` is not readable from inside the agent sandbox. The §17 Red list is therefore verified through the framework (`config:show` / `about` / `doctor`), not by opening the file. This is a restriction, not a skipped check.
5. `laravel/pao` changes tool output shape under an agent. Any check that reads a human-readable trace to decide pass/fail is a false negative here — the gate is exit codes.

## The gate (this repo's closing pipeline)

`npm run verify` (syn:check + format:check + types:check) and `composer check` (analyse + pint:test + tests). Tier 1 `npm run module:done`, Tier 2 `npm run module:codegen` when Data/enum/model/controller changed (see `rules.md` Module finalization). A change is not done until analyse, pint:test, module tests, and build all exit 0.
