# 002 — Research

Verified 2026-11-20. Installed versions come from `composer show`; behaviour comes
from the vendored source, not memory.

## Installed stack (source: `composer show`)

| Package             | Installed | Note                                                       |
| ------------------- | --------- | ---------------------------------------------------------- |
| `laravel/passkeys`  | v0.2.1    | Registration, login, confirmation and management routes.   |
| `laravel/fortify`   | v1.41.0   | Owns 2FA, password confirmation and the password pipeline. |
| `laravel/framework` | 13.x      | Session `passwordConfirmed()`, Inertia server adapter.     |

## Findings from the vendored package (`vendor/laravel/passkeys`)

1. **Registration already exists server-side.**
   `routes/routes.php:34-44` exposes `GET /user/passkeys/options`,
   `POST /user/passkeys`, `DELETE /user/passkeys/{passkey}` behind
   `auth:{guard}` + `management_middleware` (default `password.confirm`).
   No backend route work is required for US1/US2.

2. **Passkey confirmation already satisfies `password.confirm`.**
   `PasskeyConfirmationController::store` calls
   `$session->passwordConfirmed()` after a valid assertion. A passkey step-up is
   therefore a frontend concern only (US5), with no custom response class.

3. **Login options use discoverable credentials.**
   `GenerateVerificationOptions::allowCredentials(null)` returns `[]`, so the same
   endpoint serves the conditional-UI ceremony already shipped.

4. **`userVerification` is required, not preferred.**
   `GenerateVerificationOptions.php:27` sets
   `USER_VERIFICATION_REQUIREMENT_REQUIRED`. That is what makes a passkey a
   multi-factor authenticator (possession + in-person/PIN verification), which is
   the basis for not stacking TOTP on top.

5. **Events exist for both mutations.**
   `PasskeyRegistered` and `PasskeyDeleted` carry the user and the `Passkey`
   model, so audit/email wiring needs no controller override.

## External guidance

- FIDO Alliance, _Passkey Rubric_ — passkey autofill (conditional UI) is the
  silver requirement; "visible fallbacks" and "multiple passkeys allowed" are
  bronze requirements; a **hostage modal** (forced creation before access) is a
  disqualified anti-pattern. This is why US4 is dismissible and server-persisted.
  <https://www.passkeycentral.org/launch-and-grow/passkey-rubric>
- web.dev, _Sign in with a passkey through form autofill_ — `autocomplete`
  carries both tokens (`username webauthn`) so password managers keep working.
  <https://web.dev/articles/passkey-form-autofill>
- NIST SP 800-63B, AAL2 — a multi-factor authenticator satisfies AAL2; a WebAuthn
  credential with required user verification is one. No second factor is needed
  after it. <https://pages.nist.gov/800-63-4/sp800-63b.html>
- Microsoft Entra, passwordless GA — one credential is promoted, the rest sit
  behind "Other ways to sign in", and the swap is user-initiated.
  <https://techcommunity.microsoft.com/blog/microsoft-entra-blog/passwordless-authentication-is-now-generally-available/1994700>

## Repository facts that shape the plan

- The outbound mail **port already exists** (`Shared\Infrastructure\Mail\MailInterface`)
  with a Resend adapter, and `config/mail.php` defaults `MAIL_MAILER` to
  `resend`. "Plug in Resend" is already an env-only step; the new feature must
  route through the port, not add a provider.
- `HandleInertiaRequests` already shares the authenticated `UserData`; the nudge
  can be a shared boolean computed with `passkeys()->exists()`.
- `useTwoFactorSettings` already translates `password.confirm` failures into a
  `password-required` code; that translation is duplicated nowhere else and will
  be extracted to `lib/http.ts` for reuse by the passkey hook.
- `AuthenticatorLoginController` verifies the TOTP but never consults
  `AccountLockout`, unlike the passkey gate and the password pipeline. That is a
  real bypass, recorded as a finding rather than a style preference.
