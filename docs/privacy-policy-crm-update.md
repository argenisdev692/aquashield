# Privacy Policy & legal-page fixes — CRM coverage update

> Audit date: **2026-10-18** — statuses verified live against `aquashieldrestorationusa.com`.
> Scope: the official site's legal pages, plus the links the CRM app
> (`resources/js/Components/WelcomeFooter.vue`) points at them.
>
> Statuses were checked with Tavily + Firecrawl; re-verify before publishing anything derived from this doc.

## Findings (as of 2026-10-18)

| URL                            | Status                                                                         |
| ------------------------------ | ------------------------------------------------------------------------------ |
| `/privacy-policy`              | **200** — English, updated 2026-02-20, TDPSA-structured (9 sections)           |
| `/terms-and-conditions`        | **200** — Spanish only                                                         |
| `/privacy-request` (DSAR form) | **200**                                                                        |
| `/cookies-policy`              | **404** — linked twice from the privacy policy §8 and referenced by the banner |
| `/privacy`, `/terms`           | **404** (stale URLs)                                                           |

The CRM footer links **Privacy Policy** and **Terms & Conditions** only; Cookie Policy stays unlinked
until the page exists.

---

## 1. Privacy Policy — minimal patch so it covers the CRM

The existing policy is a solid base (it already covers website forms, processors, retention, TDPSA
rights). The CRM introduces job/photo/account data, so only these parts need to change.

### 1.1 §1 Categories — add three rows to the table

| Category                 | Data Elements                                                                                     | Source                                           |
| ------------------------ | ------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| Job & Service Records    | Job address, service type, scope of work, estimates, invoices, claim references, scheduling notes | Created by our team while delivering the service |
| Photos & Documentation   | Property photos, moisture readings, equipment logs, signed work authorizations                    | Created by our team while delivering the service |
| Authorized User Accounts | Name, work email, hashed password or Google account identifier, 2FA secret, last login            | Staff / authorized users                         |

Also widen the intro sentence — from
"when you use our Contact Support form, Free Inspection form, or interact with our website" to
"when you use our Contact Support form, Free Inspection form, or interact with our website, **or when
we deliver a service and manage it in our internal job-management system**".

### 1.2 §2 Purposes — add one bullet

- **Service delivery & job management** — internal coordination of inspections, mitigation,
  reconstruction and claim documentation for the service you requested.

### 1.3 §3 Processors — keep the table honest per system

- Keep the **Supabase** row, but label it "Website forms".
- Add (fill providers at deploy time):
  - `<CRM host / database provider>` — the job-management system (Laravel app) — all CRM records.
  - `<Object storage provider>` — job photos and documents.
  - **Google** — OAuth sign-in for authorized users (authentication data only).
- **Resend** is already listed ✔.

### 1.4 §4 Retention — add rows

| Record                     | Period                                                                                         |
| -------------------------- | ---------------------------------------------------------------------------------------------- |
| Job & claim records        | Project completion + `<N>` years — align with insurance and Texas limitation periods (counsel) |
| Job photos & documentation | Same as job records                                                                            |
| Backups                    | Rolling `<N>`-day window, then purged                                                          |

### 1.5 New short subsection — employees & authorized users

> **Employees and authorized users:** we process account data (name, work email, authentication
> identifiers, access logs) to provide access to internal systems and keep them secure. Access is
> governed by company policy. Contact `<privacy email>` with any question.

(Most US state privacy laws exempt HR data — California is the main exception. Keep this short.)

---

## 2. Cookie Policy — publish the page (or remove the references)

`/cookies-policy` currently 404s while §8 of the policy and the site banner link to it. Simplest fix is
publishing the page. Ready-to-publish draft:

---

**Cookie Policy** — Last updated: `<date>`

`aquashieldrestorationusa.com` uses as few cookies as possible:

- **Strictly necessary.** Session and security cookies keep forms working and block abuse. They are
  required for the site to function and are set without consent.
- **Analytics (optional).** With your consent, Google Analytics 4 measures aggregate traffic with IP
  anonymization enabled. Declining changes nothing about how the site works, and you can change your
  choice at any time from the footer.
- **No advertising cookies.** We do not use advertising or cross-site tracking cookies, and we do not
  sell or share personal data for targeted advertising.

You can also block or delete cookies in your browser settings; blocking strictly necessary cookies may
break forms and submissions.

Questions: `<privacy email>` · (713) 587-6423

---

## 3. Consistency fixes

1. **DSAR wording.** The form says "45 business days"; the TDPSA and the policy's own §5 say **45
   days**. Fix the form copy.
2. **T&C language.** Spanish-only on an English site — serve an English version (or bilingual) at the
   same URL.
3. **Stale URLs.** `/privacy` and `/terms` 404 — redirect them to `/privacy-policy` and
   `/terms-and-conditions`, or drop them from the sitemap.
4. **Link sync.** If any of these URLs change, update the CRM footer
   (`resources/js/Components/WelcomeFooter.vue` → `LEGAL`).

## 4. What is NOT needed

- **T&C for the CRM app itself** — staff tool; internal access is governed by company policy, not user
  terms. Revisit only if customers ever log in or transact.
- **A cookie banner inside the CRM** — session/auth cookies are strictly necessary and consent-exempt.
