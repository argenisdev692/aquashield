# 002 — Plan

## Decisions

### D1 — A passkey is the whole authentication, not a step

`laravel/passkeys` requests user verification on every assertion, so a passkey is
a multi-factor authenticator. The TOTP challenge is **not** added after a passkey
login. The predictor is the credential type, not a preference flag.

### D2 — TOTP as a first factor stays, hardened and documented

The route is a deliberate operator fallback and removing it is a product
decision, so this change does not delete it. It is hardened with the durable
`AccountLockout` check (parity with the passkey gate) and documented as a
single-factor compatibility path, not a recommended primary credential.

### D3 — The enrolment nudge is server-persisted and dismissible

A new nullable column `passkey_prompt_dismissed_at` on `users`. The shared prop
`passkeyPrompt` is true only for a user with zero passkeys and no dismissal.
Placement is the dashboard, the copy is benefit-led, and "Not now" writes the
timestamp. No modal, no blocking, no repeating every visit.

### D4 — Step-up reuses the package's confirmation route

`POST /passkeys/confirm` already calls `$session->passwordConfirmed()`. The
existing `ConfirmPasswordDialog` gains an optional "Use a passkey" action; no new
backend response class and no change to Fortify middleware.

### D5 — Mail goes through the existing port

`MailInterface` + `ResendMailAdapter` are already installed and Resend is the
default. `PasskeyRegistered` / `PasskeyDeleted` feed `SecurityEvent` and
`SendSecurityAlert`; the provider is selected by `MAIL_MAILER` in `.env` and the
shared transport owns the timeout and circuit breaker.

### D6 — Server state ownership

The passkey **list** is an Inertia prop (`passkeys`, `passkeysCount`). Add/delete
are JSON calls in `Modules/Auth/hooks/usePasskeys.ts` followed by
`router.reload({ only: ['passkeys', 'passkeysCount', 'passkeyPrompt'] })`. No
TanStack Query cache for data the page already owns.

## Architecture

```
Server
  ProfileController::edit ── PasskeyData[] ──┐
  HandleInertiaRequests ──── passkeyPrompt ──┤
  POST /settings/passkey-prompt/dismiss ─────┤
                                             ▼
Client (Settings/Profile.tsx)
  usePasskeys() ── /user/passkeys/options | POST/DELETE /user/passkeys
                └─ /passkeys/confirm/options | POST /passkeys/confirm
  PasskeySection ── list, add dialog, delete dialog
  SecuritySection ── summary + ConfirmPasswordDialog(onConfirmWithPasskey)
Dashboard
  usePasskeyPrompt() ── dismiss POST
  <PasskeyPrompt/> ── link to /settings/profile#security
```

## File map

### Backend

| File                                                                       | Change                                |
| -------------------------------------------------------------------------- | ------------------------------------- |
| `database/migrations/*_add_passkey_prompt_dismissed_at_to_users_table.php` | new column                            |
| `app/Data/Auth/PasskeyData.php`                                            | allowlisted passkey payload           |
| `app/Http/Controllers/Settings/ProfileController.php`                      | `passkeys`, `passkeysCount`           |
| `app/Http/Controllers/Settings/PasskeyPromptController.php`                | dismissal write                       |
| `app/Http/Middleware/HandleInertiaRequests.php`                            | shared `passkeyPrompt`                |
| `app/Models/User.php`                                                      | fillable + datetime cast              |
| `routes/web.php`                                                           | dismiss route (auth, throttled)       |
| `app/Enums/SecurityEvent.php`                                              | `PasskeyRegistered`, `PasskeyDeleted` |
| `app/Listeners/AuthEventSubscriber.php`                                    | subscribe to the package events       |
| `app/Http/Controllers/Auth/AuthenticatorLoginController.php`               | lockout guard                         |

### Frontend

| File                                                               | Change                                                                 |
| ------------------------------------------------------------------ | ---------------------------------------------------------------------- |
| `resources/js/lib/http.ts`                                         | `requestJson`, `PasswordConfirmationRequiredError`, `HttpRequestError` |
| `resources/js/Modules/Auth/hooks/useTwoFactorSettings.ts`          | use the shared helper                                                  |
| `resources/js/Modules/Auth/hooks/usePasskeys.ts`                   | ceremony + mutations + step-up                                         |
| `resources/js/Modules/Auth/hooks/usePasskeyPrompt.ts`              | dismiss mutation                                                       |
| `resources/js/Pages/Settings/components/PasskeySection.tsx`        | list / add / delete                                                    |
| `resources/js/Pages/Settings/components/SecuritySection.tsx`       | summary + step-up wiring                                               |
| `resources/js/Pages/Settings/components/ConfirmPasswordDialog.tsx` | optional passkey action                                                |
| `resources/js/Pages/Settings/Profile.tsx`                          | props, hook, composed tags                                             |
| `resources/js/Pages/Dashboard/components/PasskeyPrompt.tsx`        | banner                                                                 |
| `resources/js/Pages/Dashboard.tsx`                                 | render the banner                                                      |

### Tests

| File                                            | Covers                               |
| ----------------------------------------------- | ------------------------------------ |
| `tests/Feature/Auth/PasskeyManagementTest.php`  | props, dismissal, auth, event alerts |
| `tests/Feature/Auth/AuthenticatorLoginTest.php` | lockout parity                       |
| `tests/Feature/Auth/SettingsTest.php`           | passkey props on the page            |

## Contracts

`PasskeyData` (snake_case output): `id:int`, `name:string`, `created_at:?string`,
`last_used_at:?string`.

`POST /settings/passkey-prompt/dismiss` → `204` for JSON, back for HTML.

Shared prop `passkeyPrompt: bool|null` (null for guests).

Registration ceremony payload (package contract):
`{ name, credential: { id, rawId, type, response: { clientDataJSON, attestationObject, transports }, clientExtensionResults } }`.

## Risks and mitigations

| Risk                                                 | Mitigation                                                                        |
| ---------------------------------------------------- | --------------------------------------------------------------------------------- |
| `password.confirm` redirects HTML into a JSON call   | shared `requestJson` maps 423 / `/user/confirm-password` redirect to a typed code |
| `router.reload` after mutation races the modal close | reload on success only; dialogs close after the promise settles                   |
| Deleting the last passkey locks the user out         | password always remains; copy states it, and TOTP is unaffected                   |
| Extra query on every authenticated page              | one indexed `exists()`; acceptable, and `passkeyPrompt` is null for guests        |
| Enumeration via the settings props                   | props exist only for `auth`; no public endpoint reveals passkey presence          |
| Provider coupling in the new code                    | only `MailInterface`; no Resend class import                                      |

## Gate

`npm run module:codegen` (Data class + model + controller changed) then
`npm run module:done`. `doctor` is expected to stay red on
`Composer autoload works` (documented environment finding); everything else must
exit 0, with `composer check` run explicitly for PHPStan, Pint and Pest.
