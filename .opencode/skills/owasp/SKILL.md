---
name: owasp
description: OWASP Security Baseline — framework-agnostic enterprise core (OWASP Top 10:2025 + API:2023 + LLM:2025). Always-on for any change. Stack implementations live in owasp/STACK.md.
trigger: always_on
version: 1.0
---

# OWASP Security Baseline — Enterprise Core (portable)

> **Version:** 1.0 · **Reviewed:** 2026-09-28 · **Recertify:** when OWASP publishes a new Top 10 (historically every ~4 years; next expected ~2029–2030) or when the API/LLM Top 10s are superseded.
>
> **Sources of truth:**
>
> - **Web apps** → [OWASP Top 10:2025](https://owasp.org/Top10/) (released November 2025: NEW A03 Software Supply Chain Failures, NEW A10 Mishandling of Exceptional Conditions; SSRF folded into A01).
> - **APIs** → [OWASP API Security Top 10:2023](https://owasp.org/API-Security/editions/2023/) (still current).
> - **LLM apps** → [OWASP Top 10 for LLM Applications:2025](https://genai.owasp.org/llm-top-10/) (applies WHEN AI is in scope, §16).
> - **AI agents** → [OWASP Top 10 for Agentic Applications 2026](https://genai.owasp.org/) (applies ONLY if the project exposes autonomous agents).
>
> No official "OWASP Top 15" exists. This document merges Top 10:2025 + API Top 10:2023 + LLM Top 10:2025 into a practical 15+2-point baseline.
>
> **How to use this file:** every control below is written WITHOUT framework names on purpose — it ports to any enterprise stack (Laravel, Django, .NET, Rails + any SPA). Each control ends with a `Stack binding:` pointer to the concrete implementation for the current project. Copy this file to another repo as-is; rewrite only the bindings file.

---

## §0 — Authority and scope

- This file is **always-on**: it applies to every backend and frontend change.
- Every PR MUST satisfy the applicable baseline items or document a justified deviation (`Justified-OWASP-Deviation:` trailer referencing the checklist item).
- Stack implementations (this project): `owasp/STACK.md` — snippets, package versions, verified state, documented exceptions.
- Use live research for fresh CVEs/advisories; use versioned docs for exact package API behavior. Never rely on training memory for security-sensitive APIs.

---

## §1 — Broken Access Control + SSRF (A01:2025 + API1/API3/API5:2023)

> **2025 change**: A01:2021 (Broken Access Control) **absorbed** A10:2021 (SSRF).

- Deny by default: every route guarded by authentication unless explicitly public.
- Authorization on every mutating verb: capability/permission check or policy — never "trust the URL".
- Object identifiers in URLs are non-enumerable (UUIDs, never sequential ids); malformed ids 404 before reaching business logic.
- Object-level checks: every data access verifies ownership/tenancy before mutation.
- Property-level checks: responses shaped through an explicit allowlist (DTO/serializer). Never serialize the raw persistence model.
- UI hiding is **defense in depth, not the boundary** — the server re-checks everything. Frontend authorizes by **capabilities/permissions**, never by roles (roles are backend grouping only).
- **SSRF mitigation (now part of A01)**: outbound HTTP allowlist; block `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16` (cloud metadata), `::1`, `fc00::/7`. See §15.
- **Hard rule**: any new endpoint without explicit authorization fails review.
- _Stack binding: → STACK.md §1 (middleware, UUID route constraints, permission guard component)._

---

## §2 — Authentication Failures (A07:2025 + API2:2023)

- Web (session) flows: framework session auth + CSRF protection (token + origin verification where the framework supports it).
- Machine/API flows: scoped personal tokens (opaque random strings, NOT self-contained JWT unless the design explicitly requires it — and then with short TTL + rotation).
- Password hashing: memory-hard function (Argon2id preferred, bcrypt with high cost acceptable). Never MD5/SHA1/plain.
- Brute force: throttle login (e.g. 5/min per IP+account).
- Session security in production: `Secure`, `HttpOnly`, `SameSite=Lax` (or `Strict` for sensitive flows); encrypt cookie payload when the framework supports it.
- Login regenerates the session id (fixation); logout invalidates the session AND rotates the CSRF token.
- MFA where the risk justifies it (TOTP/WebAuthn/passkeys).
- Frontend: on logout, navigate to login + drop all cached server state (no PII left in memory stores). Never persist auth tokens in `localStorage`/`sessionStorage` when session cookies are the mechanism.
- _Stack binding: → STACK.md §2 (session env keys, throttle keys, MFA packages)._

---

## §3 — Injection (A05:2025 + API8:2023)

- ORM / parameterized queries everywhere. Raw SQL only with bound parameters — never string interpolation of user input.
- Validation gates: every input validated at the backend boundary (request validator or typed DTO with validation attributes). The backend is authoritative; client validation is UX only.
- Mass assignment: explicit allowlist per model — never a blanket unguarded flag.
- Shell execution: forbidden. If unavoidable, process API with argument arrays — never shell strings with concatenation.
- File path traversal: storage APIs only — never filesystem reads from user-supplied paths.
- Frontend: template auto-escaping is the default. Raw-HTML rendering of user input is forbidden (server-side sanitization required for rich text). Never `eval()`, never `new Function(string)`.
- _Stack binding: → STACK.md §3 (FormRequest/Data, `$fillable`, sanitizer)._

---

## §4 — Cryptographic Failures (A04:2025)

- TLS everywhere + HSTS (includeSubDomains where the domain layout allows; preload only deliberately).
- Application-level encryption for sensitive at-rest fields (PII, stored tokens) via the framework's encryptor.
- Secrets: NEVER committed. Env files carry placeholders; real values live in the deploy platform's secret store.
- Logging: logs MUST never receive passwords, tokens, signed URLs, full card numbers, or bearer credentials. Audit trails use an explicit field allowlist.
- Cookies: `Secure` + `HttpOnly` + `SameSite` (see §2).
- _Stack binding: → STACK.md §4 (encryptor, env example, audit-trail allowlist)._

---

## §5 — Security Misconfiguration (A02:2025 — UP from #5 to #2 in 2025)

- Debug mode OFF in production — non-negotiable (no stack traces, SQL errors, or file paths in responses).
- Hardened response headers: HSTS, `X-Frame-Options: DENY` (or `frame-ancestors 'none'`), `X-Content-Type-Options: nosniff`, strict `Referrer-Policy`, restrictive `Permissions-Policy`.
- CSP with an explicit allowlist. Runtime-injected styles/scripts (SPA overlays, SFC-compiled styles) force one of: **per-request nonce (preferred)** or **build-time hashes**. `'unsafe-inline'` in production is forbidden unless documented as a temporary, time-boxed deviation. No `'unsafe-eval'` in production.
- CORS: explicit origin allowlist for authenticated endpoints — never `*`. Wildcard subdomains via explicit regex patterns only.
- Client builds: minified, no production source maps, only explicitly-prefixed env keys exposed to the bundle — never secrets.
- Env keys validated at boot; default providers/pages disabled in production.
- _Stack binding: → STACK.md §5 (headers middleware, CSP nonce wiring, CORS config, build flags)._

---

## §6 — Software Supply Chain Failures (A03:2025 — NEW category)

> **2025 change**: A06:2021 (Vulnerable & Outdated Components) was **renamed and broadened** — compromised dependencies, build-time injection, typosquats, unsigned artifacts, CI/CD tampering.

- Lockfiles committed. Dependency audit runs in CI on every PR (backend + frontend).
- Pin major versions. Review changelogs before upgrades; security patches within 7 days of advisory.
- Auto-merge patch versions only (minor/major need a human).
- No packages from unknown maintainers without verification.
- _Stack binding: → STACK.md §6 (audit commands, bot config, vendor-review tool)._

---

## §7 — Insecure Design (A06:2025)

- Threat-model every flow touching: auth, file upload, external APIs, payments, signatures, multi-tenant boundaries, AI generation.
- Prefer simple, auditable flows over clever ones.
- Document trust boundaries in the module's architecture record.
- Deny-by-default applies to design, not just code: feature flags default OFF.
- _Stack binding: → STACK.md §7 (where the architecture record lives)._

---

## §8 — Software / Data Integrity Failures (A08:2025 + API10:2023)

- File uploads: always validated (MIME + size + extension) and re-encoded/re-rendered when possible (strips EXIF/payloads).
- **Signed URL TTL by sensitivity**: highly sensitive (PII, personal documents) ~5 min; standard private files ~15 min; heavy batch exports ~30 min. Never expose raw bucket URLs — always signed, always generated by an adapter (never string concatenation).
- Webhooks: HMAC signature verification on every callback. Never trust payload by source IP alone.
- CI integrity: clean installs (`--no-scripts` on untrusted runners), verified base images.
- _Stack binding: → STACK.md §8 (upload validators, storage adapter, webhook verifier)._

---

## §9 — Logging & Alerting Failures (A09:2025 + API10:2023)

> **2025 change**: renamed from "Security Logging and Monitoring Failures" — emphasis on actionable alerts, not passive logs.

- Structured logs (JSON) with correlation/request ids; distributed tracing where the topology justifies it.
- Audit trail on every state-mutating aggregate: explicit field allowlist + dirty-only + skip-empty-changes.
- Audit-worthy events (login, permission change, financial mutation, file access) always recorded.
- Alerts: failed-login bursts, permission escalations, 5xx spikes, storage 4xx, queue failures.
- **Never log**: passwords, tokens, signed URLs, bearer credentials, full card numbers.
- _Stack binding: → STACK.md §9 (logger config, audit-trail adapter, alert rules)._

---

## §10 — Mishandling of Exceptional Conditions (A10:2025 — NEW category)

> **2025 NEW**: happy-path bias — ignored edge cases, leaked traces, error-driven DoS, crashes on malformed/concurrent input. Especially relevant for AI-generated code.

- Production error responses are sanitized (generic shape; details behind the debug flag).
- Domain exceptions with business meaning, mapped to HTTP codes in one place.
- Retries on idempotent operations only, with budgets.
- Timeouts on EVERY external call (HTTP, storage, mail). Never unbounded.
- Concurrency on shared writes: transactions + row locks or optimistic versioning.
- Frontend: typed handling of transport vs application errors; action-level failures surface as actionable user messages, never raw exception text.
- _Stack binding: → STACK.md §10 (exception mapper, timeout/retry defaults, client error handling)._

---

## §11 — Broken Object Level Authorization (API1:2023)

- Every lookup-by-id: 1) load, 2) verify tenant/owner matches the caller, 3) 403 on mismatch. No "trust the URL".
- Cross-cutting checks live in policies colocated with the owning module; capability checks via the permission system.
- Test coverage: every show/update/delete endpoint has a test proving 403 for a non-owner.
- _Stack binding: → STACK.md §11 (policy location, test template)._

---

## §12 — Broken Object Property Level Authorization (API3:2023)

- Responses shaped through an allowlist DTO/serializer layer — never raw-model serialization.
- Inputs shaped through validated requests/DTOs — never raw-payload mass creation.
- ORM `$hidden`-style field hiding is fallback, not primary defense.
- Client pagination/response types mirror the backend contract (generated, not hand-written, where the toolchain supports it).
- _Stack binding: → STACK.md §12 (DTO layer, generator config)._

---

## §13 — Broken Function Level Authorization (API5:2023)

- Admin-capability routes segregated (separate group/file/prefix) under their own authorization check. Never leak an admin endpoint onto the public surface.
- Web vs machine surfaces use separate route files and middleware groups.
- Navigation/UI renders by **permission**, mirroring backend capabilities 1:1. Permission constants live in ONE source — never scattered string literals.
- _Stack binding: → STACK.md §13 (route files, permission enum source, sidebar guard)._

---

## §14 — Unrestricted Resource Consumption (API4:2023 + API6:2023)

- Rate limiting per surface (auth ≪ default; expensive operations like exports ≪≪ default).
- Pagination MANDATORY on every list endpoint (sane default, hard cap).
- Upload size capped at the smallest reasonable value per use case.
- Expensive work (exports, media, AI) runs on separated workers/queues with memory/time bounds.
- External calls carry timeouts; client lists declare cache lifetimes; search inputs are debounced; submits are double-submit-guarded.
- _Stack binding: → STACK.md §14 (limiter keys, pagination helper, queue topology, debounce default)._

---

## §15 — Unsafe Consumption of APIs / SSRF (folded into A01:2025 + API10:2023)

> Kept as a separate item for operational clarity.

- Outbound URLs: explicit allowlist before fetching any user-derived URL.
- Block private/metadata ranges (see §1) via DNS validation, not just string checks.
- Validate every third-party response: schema + status + content-type.
- Webhook callbacks: verify origin (HMAC) + validate payload shape + require idempotency keys.
- Object storage only through an adapter; signed URLs only.
- _Stack binding: → STACK.md §15 (HTTP client defaults, storage adapter)._

---

## §15.5 — Sessions, URL protection, forms (operational hardening)

- **Sessions**: encrypted cookie payload; `Secure`+`HttpOnly`+`SameSite`; explicit domain decision; finite lifetime + idle sliding timeout; session id regenerated at login; full invalidate + token rotation at logout.
- **URLs**: non-enumerable ids; signed (tamper-proof, optionally expiring) routes for public-but-protected links; every public/auth endpoint throttled; object-level authorization on every id-bound handler (IDOR prevention).
- **Forms**: CSRF on every mutating form; bot trap (honeypot) on public forms; double validation (client schema for UX + server schema authoritative — server wins on conflict); submissions rate-limited; uploads validated (MIME+size+extension) and stored on the private disk with signed-URL delivery; one-time codes for confirmations/MFA via a dedicated OTP mechanism.
- _Stack binding: → STACK.md §15.5 (env keys, login/logout code, idle middleware, signed routes, honeypot, Zod+DTO pair, upload validator, OTP package)._

---

## §16 — LLM / GenAI Security (OWASP Top 10 for LLM Applications:2025)

> Applies WHEN a module calls a model provider (direct SDK or queue worker) — every LLM call goes through ONE gateway interface enforcing the five controls below. Never call a provider SDK from a controller or use-case handler.

| LLM Top 10:2025                            | Project Mitigation                                                                                                                                          |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **LLM01 Prompt Injection**                 | User text is untrusted input even inside system prompts. Structured templates with delimiters; never concatenate raw user input into a tool-calling prompt. |
| **LLM02 Sensitive Information Disclosure** | Strip PII before sending upstream. Sign DPAs with providers. Never send signed URLs or session tokens to models.                                            |
| **LLM03 Supply Chain**                     | Pin model versions (dated snapshot, not the floating alias). Audit fine-tuning datasets.                                                                    |
| **LLM04 Data and Model Poisoning**         | Allowlist RAG ingestion sources. Quarantine user-uploaded embedding content until reviewed.                                                                 |
| **LLM05 Improper Output Handling**         | LLM output is **untrusted user input**: never eval, never raw-HTML render, never auto-execute SQL/filesystem writes.                                        |
| **LLM06 Excessive Agency**                 | Explicit tool allowlist per role. Human-in-the-loop for any mutation beyond read-only queries.                                                              |
| **LLM07 System Prompt Leakage**            | No secrets in system prompts. Treat the system prompt as public.                                                                                            |
| **LLM08 Vector and Embedding Weaknesses**  | Row-level authorization on RAG queries (per-tenant index or filter). Sanitize embedded content.                                                             |
| **LLM09 Misinformation**                   | "AI-generated" labels. Citations/sources when grounding on internal data.                                                                                   |
| **LLM10 Unbounded Consumption**            | Per-user rate limit on LLM calls. Token budget per request. Circuit breaker in the gateway.                                                                 |

- _Stack binding: → STACK.md §16 (gateway interface, redactor, budgets)._

---

## OWASP Baseline Checklist — every PR

Every pull request MUST satisfy **all** applicable items or document a justified deviation.

- [ ] **1. Access Control + SSRF (§1)** — auth + capability/policy on every route; non-enumerable ids; outbound allowlists.
- [ ] **2. Authentication (§2, §15.5)** — memory-hard hashing; login throttled; session regenerated at login, invalidated at logout; secure cookies; no tokens in web storage.
- [ ] **3. Injection (§3)** — parameterized data access + boundary validation everywhere; allowlist mass assignment; no raw-HTML user input; no shell concat.
- [ ] **4. Cryptography (§4)** — TLS+HSTS; secrets via platform store only; at-rest PII encrypted; secure cookie flags.
- [ ] **5. Misconfiguration (§5)** — debug off; hardened headers + nonce/hash CSP; explicit CORS allowlist; no prod source maps.
- [ ] **6. Supply Chain (§6)** — lockfiles committed; dependency audit in CI; pinned majors.
- [ ] **7. Insecure Design (§7)** — threat-modelled for auth/upload/payment/external/AI flows; deny-by-default flags.
- [ ] **8. Integrity (§8)** — uploads validated + re-encoded; webhook HMAC verified; signed URLs only.
- [ ] **9. Logging (§9)** — no secrets in logs; allowlist audit trail on mutating aggregates.
- [ ] **10. Exceptional Conditions (§10)** — timeouts + idempotent-only retries; sanitized prod errors.
- [ ] **11. Object-level authz (§11)** — owner/tenant check before mutation; 403-tested.
- [ ] **12. Property-level authz (§12)** — allowlist response/input shaping; no raw-model serialization.
- [ ] **13. Function-level authz (§13)** — admin surface segregated; UI mirrors backend capabilities from one permission source.
- [ ] **14. Resource consumption (§14)** — throttles; mandatory capped pagination; bounded background work.
- [ ] **15. SSRF + storage (§15)** — outbound allowlist; private-range blocking; signed-URL-only storage.
- [ ] **+1. Sessions / URL / Forms (§15.5)** — session hardening set; signed routes where needed; CSRF + double validation on mutating forms.
- [ ] **+2. LLM (§16)** — IF AI is in scope: single gateway, PII redaction, budgets, untrusted-output handling, pinned models.

---

## Cross-cutting enforcement rules (always)

- **Validate every input** at the backend boundary (server-authoritative).
- **Authenticate + authorize every route**. No exceptions for "just an internal endpoint".
- **Never log secrets / tokens / PII** — audit trails use explicit allowlists.
- **Prefer secure defaults**: deny by default, no implicit trust, no broad CORS, no debug in prod.
- **Treat external integrations as untrusted** until validated: schema + signature + timeout.
- **Production errors are sanitized**: no stack traces, no SQL errors, no file paths.
- **Authorization & input validation tested**: every endpoint has 401/403/422 cases.
- **Review checklist** for every PR: this file's checklist + the stack's closing gate (→ STACK.md).

---

## Numbering map — 2021 → 2025 (for legacy docs)

| 2021                                           | 2025                                      | Notes                 |
| ---------------------------------------------- | ----------------------------------------- | --------------------- |
| A01 Broken Access Control                      | A01 Broken Access Control                 | absorbs A10:2021 SSRF |
| A02 Cryptographic Failures                     | A04 Cryptographic Failures                | ↓                     |
| A03 Injection                                  | A05 Injection                             | ↓                     |
| A04 Insecure Design                            | A06 Insecure Design                       | ↓                     |
| A05 Security Misconfiguration                  | A02 Security Misconfiguration             | ↑                     |
| A06 Vulnerable and Outdated Components         | A03 Software Supply Chain Failures        | renamed + broadened   |
| A07 Identification and Authentication Failures | A07 Authentication Failures               | renamed               |
| A08 Software and Data Integrity Failures       | A08 Software and Data Integrity Failures  | unchanged             |
| A09 Security Logging and Monitoring Failures   | A09 Logging & Alerting Failures           | renamed               |
| A10 Server-Side Request Forgery                | (folded into A01)                         | —                     |
| (none)                                         | A10 Mishandling of Exceptional Conditions | NEW                   |
