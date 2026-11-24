# 002 — Passkey security posture

**Status:** accepted · **Date:** 2026-11-20 · **Owner:** auth surface (`app/`, `src/Modules/Users`)

## Problem

The application can sign in with a passkey but cannot create or delete one. The
account screen only manages TOTP, reports "2FA off" for a user who signs in with
a passkey, and gives no path for a new user to enrol one. The result is a
passwordless button with no supply of credentials, and a security-status badge
that describes the wrong thing.

## User stories

### US1 — Enrol a passkey

As a signed-in user I can add a passkey from Settings → Security, name it, and
see it listed with its creation date, so that I can sign in without a password.

**Acceptance criteria**

- The Security tab lists every passkey with name, created date and last-used date.
- "Add a passkey" runs the WebAuthn registration ceremony and requires a recent
  password confirmation (`password.confirm`).
- A browser without WebAuthn support shows an explanation instead of a dead button.
- At least two passkeys are allowed; the UI never blocks adding one.
- The list refreshes after a successful add without a full page reload.

### US2 — Remove a passkey

As a signed-in user I can delete a passkey, with the destructive action confirmed,
and I am told what still works afterwards.

**Acceptance criteria**

- Delete opens a confirmation dialog naming the passkey.
- The confirmation states that the password (and TOTP, when enabled) still work.
- Deletion is scoped to the owner; another user's id returns 403 (package route).
- The list refreshes after deletion.

### US3 — Understand the real security state

As a signed-in user I see a composed status — passkeys, TOTP — instead of a
single boolean, so that "2FA off" never appears next to an account that signs in
with a phishing-resistant credential.

**Acceptance criteria**

- The account header shows one tag per factor present (passkeys × N, TOTP on/off).
- The Security tab shows the same summary above the cards.
- A user with a passkey and no TOTP is not described as unprotected.

### US4 — Be invited to enrol, without being forced

As a password user with no passkey I see a dismissible prompt after signing in,
so that I discover the stronger credential without a modal or a forced flow.

**Acceptance criteria**

- The dashboard shows the prompt only when the user has zero passkeys and has not
  dismissed it.
- Dismissing persists server-side (survives a new device and a new session).
- The prompt links to Settings → Security and has an explicit "Not now".
- It never appears for a user who already has a passkey.

### US5 — Step up with a passkey

As a passkey user I can satisfy the `password.confirm` gate with my passkey
instead of typing my password, for 2FA and passkey management changes.

**Acceptance criteria**

- The confirmation dialog offers "Use a passkey" when the account has one and the
  browser supports WebAuthn.
- A successful assertion marks the session password-confirmed and resumes the
  parked action.
- If the ceremony fails or is cancelled, the password form remains available.

### US6 — Honest documentation

**Acceptance criteria**

- `src/Modules/Users/ARCHITECTURE.md` states that a passkey login does not trigger
  the TOTP challenge, why, and that the first-factor TOTP route is a hardened
  compatibility fallback rather than a recommended primary credential.

### US7 — Passkey changes are audited and emailed

**Acceptance criteria**

- Adding or deleting a passkey writes an audit entry and dispatches a queued
  security email through the existing `MailInterface` port.
- The email provider is Resend (`MAIL_MAILER=resend`); the shared mail transport
  owns the timeout and circuit breaker, and no SDK name appears in the new code.

## Out of scope

- Removing the first-factor TOTP route (product decision; hardened instead).
- Passkey as a second factor of a password login (`laravel/passkeys` does not
  model it).
- Synced-passkey / AAGUID analytics.
- Cross-device enrolment guidance beyond the FIDO "add another device" hint.
- Any change to the login page hierarchy (delivered in the previous change).
