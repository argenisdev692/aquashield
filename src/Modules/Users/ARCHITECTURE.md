# Users — ARCHITECTURE.md (FLAT variant, the default)

> Copy of `stubs/architecture/ARCHITECTURE.FLAT.md`, filled in.
> Full rules → `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`.
> `tests/Architecture/ModuleComplexityTest.php` parses the `mode:` line.

mode: FLAT

## Why FLAT

One aggregate (`App\Models\User`) plus vendor-owned Role/Permission/activitylog.
The module's load-bearing parts are (a) the privilege ceiling, (b) the staff
lifecycle (`invited → active ⇄ suspended`) and (c) the grant-set guard. All three
fit in a policy, one support class and the actions; none needs a pure `Domain/`
model, a repository port or a command bus.

`User` remains the Fortify/auth aggregate. Moving it into an
`Infrastructure/Persistence/Eloquent` namespace would drag Fortify, passkeys,
2FA, Sanctum and the public registration guard with it — a large, risky move
that buys no testable boundary. `Role` and `Permission` are Spatie's domain, and
the architecture skill explicitly lists `laravel-permission` as "never
PROGRESSIVE".

## Triggers evaluated (1 of 4 — FLAT)

- [ ] **≥3 models with business rules** — the module still owns one aggregate.
      `Role` and `Permission` are Spatie models, and `activitylog` owns the audit
      persistence; `SKILL-ARCHITECTURE.md` §4 lists both as "never" for
      PROGRESSIVE. A separate invitation table was considered and rejected: the
      signed URL plus `invitation_sent_at` is a single-use marker with one
      writer, and a token table would add a second lifecycle to keep in sync.
- [x] **Invariants** — the privilege ceiling and the lifecycle: - an actor cannot grant a role or permission they do not themselves hold; - a Super Admin cannot suspend, delete or demote their own active account; - the last ACTIVE `SUPER_ADMIN` cannot be suspended, deleted or demoted
      (a suspended or invited super admin is not a usable administrator); - an invited account cannot sign in until the signed, single-use link is
      consumed; a resend invalidates every older link; - a suspended account loses its sessions and API tokens immediately.

      Enforced by `Policies/UserPolicy` + `Support/AccessCeiling` +
      `Actions/UpdateUsersStatusAction` + `Actions/ActivateUserAction`, not by a
      `Domain/` layer.

- [ ] **Tests that substitute persistence/external** — `Queue::fake()` and
      `Notification::fake()` are normal Laravel test doubles for a queued mail,
      not evidence that the module's core needs a port. The permission tables
      _are_ the behaviour; mocking them would assert the mock. The lifecycle is
      tested through the real HTTP surface and the real database.
- [ ] **Roadmap: extraction to a microservice** — users are the identity root
      (Fortify, 2FA, passkeys, sessions); extraction would drag auth with it.

## Boundaries and public contracts

- Response/input shapes: `Data/` (Spatie Data). `ManagedUserData` is the
  allowlisted admin payload (including `status`, invitation/activation dates
  and `must_change_password`); `CreateUserData`/`UpdateUserData` are inputs.
- Lifecycle vocabulary: `Enums/UserStatus` (`invited`, `active`, `suspended`).
  The cast lives on `App\Models\User`; the enum is the only server-side
  definition.
- Row-level rule: `Policies/UserPolicy` (ceiling, self-protection, last active
  super admin, suspend/activate/resend abilities). This is the declared
  exception to the "no per-module policy" default: no `permission:*` string can
  express it.
- Capability gate: `Gate::define('manage-staff')` in `UsersServiceProvider`.
  The route group uses `can:manage-staff`; a non-super-admin gets 403 before
  validation, policy or controller. The frontend receives the boolean
  `canManageStaff` capability, never the role list.
- Grant-set rule: `Support/AccessCeiling`. One definition consumed by the
  policy, the create/assign actions and the lifecycle actions.
- Read contract: `Queries/UserListQuery` is shared by `index` and all three
  export formats, so a filter cannot exist on screen but be missing from a CSV.
  `status` is the soft-delete axis; `account_status` is the lifecycle axis. The
  export carries the same `VIEW_ANY_USER` check as `index` plus `EXPORT_USER`,
  and records `user.exported` before streaming.
- Export mechanics: `Shared\Domain\Ports\ExportPort` only. The module ships
  `Exports/UserExportTransformer` + `resources/views/exports/users.blade.php`.
- Access-array validation: `Shared\Application\Rules\ExistingRoleNames` /
  `ExistingPermissionNames` resolve the whole selection in one `whereIn`. A
  per-item `exists` is one query per item and the access forms legitimately
  submit the full set (OWASP API4).
- Audit: `Shared\Domain\Ports\AuditPort` (explicit actions). No `LogsActivity`
  on `User`: the auth model is updated by Fortify too, and passive logging there
  would mix self-service profile edits with administrative actions. Events:
  `user.created`, `user.updated`, `user.deleted`, `user.restored`,
  `user.roles.assigned`, `user.permissions.assigned`, `user.status.changed`,
  `user.invitation_resent`, `user.activated`, `user.password_reset_link_sent`,
  `user.exported`.
- Onboarding: an administrator never sets, sees or emails a password on the
  default path. The account is created `invited`, unverified, with an unknown
  random credential; `Jobs/SendUserInvitationJob` mails a
  `URL::temporarySignedRoute` link (48 h) carrying the `invitation_sent_at`
  timestamp. The job is queued on `emails`, rate-limited per recipient and
  globally, and deduplicated by user + timestamp; the shared mail transport
  owns the HTTP timeout and circuit breaker. The activation endpoint compares
  the timestamp, sets the password, verifies the email, flips the status to
  `active` and clears the marker in one transaction. A resend rewrites the
  marker, invalidating older links without a second table.
- Manual override: `send_invitation=false` requires a temporary password,
  creates an `active` verified account with `must_change_password=true` and no
  invitation mail. `EnsureAccountIsActive` keeps that account on the password
  tab until Fortify's update action clears the flag.
- Suspension: `UpdateUsersStatusAction` revokes access through
  `Support/AccessRevocation` (Sanctum tokens, plus database sessions when that
  is the session store) in the same transaction;
  `App\Http\Middleware\EnsureAccountIsActive` terminates an existing web session
  on its next request.
- Real-time uniqueness: `GET /users/availability` is a throttled probe over the
  two allowlisted columns, including soft-deleted rows (their unique index still
  occupies the value). `Rule::unique(...)->ignore(...)` remains authoritative.
- What is NEVER exposed: the persistence model, `id`, password hashes, remember
  tokens, Google ids, 2FA secrets.

## Applicable invariants (see skill §3)

1. Controllers validate transport input, invoke one use case (or one read
   query), and map the response — `Http/Controllers/UserController.php`.
2. No `Domain/`, `Application/`, `Infrastructure/` folders (the architecture
   test fails them for a FLAT module).
3. Responses are Spatie `Data`, never `JsonResource`.
4. Bulk delete + bulk restore ship together, both tested.
5. Every class has a consumer — no speculative folder.

## Why the optional folders exist

- `Policies/` — the skill permits a module policy only when it owns a rule a
  permission string cannot express; the ceiling and the lifecycle guards are
  exactly that.
- `Queries/` — `index` and the export must share one filter definition (DRY
  knowledge, not shape). `UserAccessCatalogQuery` keeps the assignment screen's
  read out of the controller.
- `Support/` — the ceiling has three consumers (policy + two assign actions, and
  the create/status actions), so it is a named rule, not a helper folder.
  `AccessRevocation` is the same shape for "this account loses access now"
  (delete, suspend, activate) — three consumers, one definition.
- `Jobs/` — the invitation and the password-set link are external calls that
  must not run inside the request or the transaction.
- `Actions/` — the lifecycle operations (`ActivateUserAction`,
  `ResendInvitationAction`, `UpdateUsersStatusAction`, `AssignRoleToUsersAction`)
  are one-use-case classes because each has a transaction, a guard and an audit
  event. They are not a command bus; there is no interface and no mediator.

## Sign-in posture (Fortify + `laravel/passkeys`)

- **A passkey is a first factor, and it is already multi-factor.**
  `laravel/passkeys` requests `userVerification: 'required'` on every assertion,
  so the credential proves possession (the private key) **and** user presence
  (biometrics/PIN). No TOTP challenge is added after a passkey login. Stacking a
  phishable second factor behind a phishing-resistant credential would lower the
  assurance to the weaker link and add friction to the safest path.
- **The password path keeps its challenge.** A password login for an account with
  a confirmed TOTP secret still goes to `/two-factor-challenge`. That is the one
  transport where a second factor is doing real work.
- **The passkey routes are package-owned.** `GET /user/passkeys/options`,
  `POST /user/passkeys` and `DELETE /user/passkeys/{passkey}` come from
  `laravel/passkeys` behind `auth` + `password.confirm`; the module must not
  re-implement them. `Settings/Profile` is the only UI, and the list is an Inertia
  prop (`passkeys`, `passkeysCount`) reloaded after each mutation.
- **Step-up is shared.** `POST /passkeys/confirm` calls
  `$session->passwordConfirmed()`, so a passkey assertion satisfies the same gate
  as the password dialog. `ConfirmPasswordDialog` offers both; the password
  remains the fallback.
- **Enrolment is invited, never required.** `passkey_prompt_dismissed_at` records
  the "Not now" choice server-side and the dashboard banner is the only consumer.
  A forced modal is a FIDO anti-pattern (hostage modal) and is not used.
- **Passkey mutations are audited and emailed.** `PasskeyRegistered` /
  `PasskeyDeleted` feed `AuthEventSubscriber` → `SecurityEvent` →
  `SendSecurityAlert` → `Shared\Infrastructure\Mail\MailInterface`. The outbound
  mailer is Resend (`MAIL_MAILER=resend`); the shared transport adds the HTTP
  timeout and circuit breaker. No adapter is named in the auth code.

### TOTP as a first factor

`POST /login/authenticator` (`AuthenticatorLoginController`) is a compatibility
fallback for operators who already enrolled TOTP, **not** a recommended primary
credential: a TOTP code is a single, phishable factor. It is retained because
removing it is a product decision, but it:

- enforces the durable `AccountLockout` (parity with the password and passkey
  transports; without it, the route was a way around a lock),
- refuses unverified email,
- is throttled by `authenticator-login`,
- does not enrol 2FA from the login screen.

Tracked for removal in a future auth cleanup; until then it must not be
advertised as equivalent to a passkey.

## Accepted debt

- `User` stays in `app/Models` because it is the Fortify/auth aggregate. The
  module operates on it instead of moving it and dragging auth across the tree.
- `SUPER_ADMIN` and the Users capability names live in
  `Shared\Domain\Enums\Role` / `Shared\Domain\Enums\Permission`. The seeder's
  full role catalogue and 505-name matrix stay data; the names used by routes,
  policies, requests and controllers come from the enums.
- The ceiling is defence in depth beneath `can:manage-staff`. Every actor who
  can reach the module is a `SUPER_ADMIN`, and `canManage()` returns true for a
  super admin by definition; the row-level rules are therefore enforced by
  `Tests/Unit/AccessCeilingTest.php` (direct action/policy calls), while the
  HTTP suite proves the gate. Widening the gate to a delegated-admin capability
  is a product decision and would make the ceiling live end-to-end.
- No custom cache. spatie/laravel-permission caches the permission registry for
  24 hours and flushes it on every role/permission change; a second cache would
  need a second invalidation path.
- Exports are synchronous. Tabular exports are capped at 10,000 rows and PDF at
  500: dompdf renders the whole table inside the request, so the PDF cap is
  lower on purpose. A queued export is a separate feature; the cap reports
  rather than truncates.
- Admin-created/changed accounts are e-mail-verified by the administrator. This
  is deliberate for an internal staff workflow; a verification wall would lock
  the new user out of the screens they were created for.
- Inertia pages are not part of this backend task. The JSON contract (`rows` +
  `total`, `filter[...]`, `sort`, `per_page`) is the DataTable kit's contract.
  The Inertia screen set now consumes the same routes; the list still exposes
  the JSON body to the DataTable.
- Password policy is 8–15 characters, the pre-existing product decision recorded
  in `App\Actions\Fortify\PasswordValidationRules`. NIST SP 800-63B-4 (2025)
  and the OWASP Authentication Cheat Sheet ask for 15 characters when the
  password is the sole factor and a 64-character maximum; this 15-character
  ceiling remains a product decision that also changes the mirrored TS
  constants and the demo credential. Tracked, not silently changed here.

## Tests holding the module down

- `Tests/Feature/UserCrudTest.php` — 401/403/422, index/filter/sort, show,
  create (invitation and temporary-password paths), update, delete/restore,
  bulk pair, audit rows, unique-constraint race mapped to 422.
- `Tests/Feature/UserAccessTest.php` — catalog, role/direct-permission
  assignment, the delegated-manager 403, self-protection, last super admin.
- `Tests/Feature/UserActivationTest.php` — signed link render, activation,
  reuse rejection, expiry, resend invalidation, password policy.
- `Tests/Feature/UserAvailabilityTest.php` — free/taken, normalisation,
  ignore-self, soft-deleted rows, malformed values, 403/401.
- `Tests/Feature/UserBulkActionsTest.php` — status, role, resend, delete and
  restore summaries, self/eligible-row skips, payload validation,
  delegated-manager 403.
- `Tests/Feature/UserInvitationResilienceTest.php` — superseded timestamp,
  job rate limit/unique id, bulk-route throttle.
- `Tests/Feature/UserStatusLifecycleTest.php` — suspended/invited login refusal,
  existing-session termination, token/session revocation, forced password change.
- `Tests/Feature/UserSuperAdminGateTest.php` — the management surface is 403
  for a delegated admin and 401 for a guest: index, show, create, edit,
  catalog, availability, export, store, update, delete, single/bulk restore,
  invitation, password reset, roles, permissions, and the four
  status/role/invite/delete bulk verbs; also a super admin stripped of a
  fine-grained permission.
- `Tests/Feature/UserExportTest.php` — CSV/XLSX/PDF, same filters as the list,
  403 without `EXPORT_USER` or `VIEW_ANY_USER`, formula-injection neutralisation.
- `Tests/Feature/UserPagesTest.php` — Inertia screens, deep-linked filters,
  page-level abilities, JSON/Inertia transport split, shared `authPermissions`.
- `Tests/Feature/UserPasswordFlowTest.php` — branded invitation mail, active
  password-reset mail, admin resend + audit, ceiling refusal, email-change
  verification and old-address security alert.
- `tests/Feature/Auth/PasskeyManagementTest.php` — allowlisted passkey props,
  owner-scoped list, server-side prompt dismissal, guest rejection, and the
  audited/queued alert on add/delete.
- `tests/Feature/Auth/AuthenticatorLoginTest.php` — TOTP-first sign-in, including
  the durable-lockout refusal.
- `tests/Feature/Auth/PasswordPolicyTest.php` — 8–15 boundary on invitation
  activation and reset, self-registration disabled.
- `Tests/Unit/AccessCeilingTest.php` — the ceiling, the last-active guard, the
  suspended-admin exclusion and the view-policy ceiling, called directly.
- `Tests/Unit/UserFilterDataTest.php` — the DataTable/export query contract.
- `Tests/Unit/Users/UserPermissionEnumTest.php` — the shared permission enum is
  a subset of the seeder's matrix.
- `tests/Feature/Mail/MailTransportConfigTest.php` and
  `tests/Unit/Mail/CircuitBreakerTransportTest.php` — explicit timeouts and the
  mail circuit breaker.

## Mode review (the FLAT side of the OCP bet)

- Declared FLAT on: 2026-11-23.
- Review due: 2027-05-23 (two quarters).
- What would promote it: a second consumer that needs the lifecycle rules
  without the database (e.g. a queue-only provisioning service), or a third
  owned model with business rules. Neither exists today.
- What would demote it: nothing — it is already FLAT. If the invitation marker
  grows a second state machine, revisit before adding a table, not after.
