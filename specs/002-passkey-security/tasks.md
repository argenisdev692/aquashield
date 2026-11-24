# 002 — Tasks

Every FR maps to at least one task. `[x]` is written only after the gate step
that proves it has run.

## T1 — Passkey list in Settings (US1, US2)

- [x] `PasskeyData` Data class + `PasskeyData::fromModel`
- [x] `ProfileController` exposes `passkeys` and `passkeysCount`
- [x] `typescript:transform` regenerates `generated.d.ts`
- [x] `PasskeySection` renders name, created, last used
- [x] Add dialog with name field, WebAuthn ceremony, unsupported-browser state
- [x] Delete dialog with owner-scoped copy
- [x] `router.reload(['passkeys','passkeysCount','passkeyPrompt'])` after mutation

## T2 — Composed security status (US3)

- [x] Header tags: passkeys × N + TOTP on/off
- [x] Security tab summary line
- [x] No "2FA off" for a passkey-only account

## T3 — Enrolment nudge (US4)

- [x] Migration `passkey_prompt_dismissed_at`
- [x] `User` fillable + cast
- [x] Shared `passkeyPrompt` prop
- [x] Dismiss route + controller
- [x] `usePasskeyPrompt` + banner on the dashboard
- [x] Banner links to `#security` and never renders for passkey holders

## T4 — Passkey step-up (US5)

- [x] `usePasskeys.confirmWithPasskey()`
- [x] `ConfirmPasswordDialog` optional action
- [x] `SecuritySection` wires the action, password remains the fallback

## T5 — Audit + email (US7)

- [x] `SecurityEvent::PasskeyRegistered` / `PasskeyDeleted`
- [x] `AuthEventSubscriber` listens to the package events
- [x] Alerts go through `SendSecurityAlert` → `MailInterface` (Resend default)
- [x] Test asserts the audit/notification path without a provider call

## T6 — TOTP-first hardening + docs (US6, D2)

- [x] `AccountLockout::active()` guard in `AuthenticatorLoginController`
- [x] Test: locked account is refused
- [x] `src/Modules/Users/ARCHITECTURE.md` records the passkey/2FA posture

## T7 — Shared JSON transport

- [x] `lib/http.ts` exports `requestJson` + typed errors
- [x] `useTwoFactorSettings` consumes it with identical behaviour
- [x] `usePasskeys` consumes it

## T8 — Verification

- [x] `npx tsc --noEmit`
- [x] `npm run build`
- [x] `php artisan test --compact` — 474 passed, 2015 assertions
- [x] `composer analyse` (0 errors) + `composer pint:test`
- [x] Graph re-index + `check_index_coverage` on every touched path

## Verification log

| Step                  | Result                                                                            |
| --------------------- | --------------------------------------------------------------------------------- |
| `npm run verify`      | green                                                                             |
| `vite build`          | green (pre-existing `Welcome` chunk warning)                                      |
| PHPStan L5            | 0 errors                                                                          |
| Pint `--blade --test` | passed                                                                            |
| Pest                  | 474 passed / 2015 assertions                                                      |
| `doctor`              | expected red — `Composer autoload works` timeout (documented environment finding) |
