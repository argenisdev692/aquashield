# LeadAppointment — ARCHITECTURE.md (PROGRESSIVE)

> One module for the lead-to-inspection flow: public intake, pipeline, company
> availability, appointments, calendar, Google Calendar sync, exports and the
> source report. Rules → `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`.
>
> Review entry point, size budget, change amplification and the legacy
> `TABLA-APPOINMENT-AQUASHIELD` mapping → `README.md`.

mode: PROGRESSIVE

## Why PROGRESSIVE (3/4 triggers met)

- [x] ≥3 models with business rules: `Lead`, `Appointment`,
      `AvailabilityRule`, `AvailabilityException`.
- [x] Invariants: one 7 h inspection plus 1 h travel per day for the single
      crew; the travel buffer blocks the next visit; availability is company
      exception > company rule > closed; lead/appointment status transitions are
      explicit; duplicate leads are linked, never hard-blocked.
- [x] Tests that swap out persistence/external: DB-free action tests with mocked
      ports; Google Calendar, Meta Graph and the US holiday provider are substituted.
- [ ] Extraction roadmap: none. This is one bounded context for a single company.

## Boundaries and public contracts

- Public API: `POST /api/leads`, `GET /api/leads/honeypot`, and the Meta
  webhook. No auth; rate-limited, honeypot + content scoring + HMAC.
- Public web/JSON admin surface: leads, appointments, calendar feed,
  availability, reports and exports, all session-authenticated and
  permission-gated.
- Ports other modules may use: none. This module consumes
  `Shared\Domain\Ports\ExportPort`, `AuditPort`, `Shared\Infrastructure\Mail\MailInterface`,
  `Shared\Application\DTOs\BulkUuidsData`, `Shared\Support\SpreadsheetCell`,
  `Shared\Infrastructure\Company\CompanyProfile` and
  `Shared\Infrastructure\Resilience\CircuitBreakerInterface`.
- Events emitted: `LeadCaptured`, `AppointmentScheduled`,
  `AppointmentRescheduled`, `AppointmentCancelled`.
  Listeners live in this module's `Infrastructure/Listeners/` because they are
  side-effect adapters (mail, Google Calendar).
- What is NEVER exposed: Eloquent models from an API response, internal numeric
  ids, `Domain/`, PII in logs, the Meta app secret/page token.

## Applicable invariants (skill §3)

1. `Domain/` imports no framework, ORM, HTTP, facades, `Application/`, `Http/` or
   `Infrastructure/`. `Domain/Ports` is the only outward seam.
2. `Application/` imports Domain, own DTOs/ports and `Illuminate\Contracts\*`.
   No Eloquent, no facades (`DB`, `Cache`, `Log`), no `Illuminate\Http`.
3. Controllers validate → invoke one Action/Query for a multi-step flow, or one
   Application port for one-step CRUD → map a Data response. A forwarding
   Action is a dumb pass-through, not a use case.
4. Repository implementations live in `Infrastructure/Persistence/Eloquent/` and
   implement a port with a different name.
5. Transactions and row locks live in repositories, never in Application actions.
6. No module reaches another module's `Domain/` or `Application/`.
7. Every class has a consumer; no `Contracts/`, `Interfaces/`, `Abstracts/`,
   `Base/` or `Traits/` folder.
8. No dumb pass-through: a method that only forwards its own arguments is a
   failing test (`PRINCIPLES/SKILL.md` §4).

## This is an OCP bet — name it and date it

- Bet made on: **2026-11-19**
- Review due: **2027-05-19** (two quarters)
- What would prove this was over-engineering: no second consumer of the
  availability/scheduling ports by the review date and no test-covered invariant
  beyond the ones in this file.

## Accepted debt and de-escalation condition

- The public booking model is request-only; there is no visitor-facing slot
  picker. Staff schedule; this is deliberate.
- Technician assignment is out of this phase. When Claim + Scope Sheet assigns
  an inspector, it references a `User` with the `TECHNICAL_SERVICES` role — no
  parallel technicians table. Google Calendar stays one shared company calendar.
- `Meetings`, `Jobs`, `Customers` and multi-property accounts are separate specs.
  The reference `Meeting` aggregate (organizer + polymorphic attendees + Google
  Meet link) was deliberately not ported; only `AppointmentKind::AdjusterMeeting`
  and `LeadActivityType::Meeting` exist as values.
- Services and the technician catalog are **not** part of this phase (removed
  2026-11-23). Both belong to the Claim + Scope Sheet flow that starts after the
  insurance claim is signed; this module stops at the booked inspection. The
  `services` table, `service_technician` pivot, `technicians` table and the
  `technician_id` columns no longer exist. The inspector is a `User` with the
  `TECHNICAL_SERVICES` role when that phase assigns one.
- The legacy lead columns (`insurance_property`, `message`, `intent_to_claim`,
  `follow_up_date`, `inspection_date`, `inspection_time`, `inspection_status`,
  `additional_note`, `latitude`, `longitude`) live on `leads`. When the operator
  marks a lead as called, the inspection date/time is recorded on the lead; it
  does not create an Appointment or assign an inspector. The confirmed visit
  still lives on `appointments`, and `LeadData` prefers it when one exists.
- `estimate_sent` is deliberately absent. The estimate signature is owned by the
  future Claim + Scope Sheet modules; this module stops at the inspection visit.
- `leads.region` is nullable while the Google Maps autocomplete contract is
  settled. Scheduling falls back to the request region, then
  `config('lead-appointment.default_region')`; a lead without a metro is valid.
- `EmailSpamGuard` hard-rejects reserved/placeholder/disposable domains on the
  public form and scores role mailboxes and suspicious local parts. The lists
  live in `config/lead-appointment.php`; DNS/MX and catch-all checks are a
  future queue job, not a synchronous request.
- `last_contacted_at` is a timestamp, not a full lead-to-contact event store; the
  `lead_activities` table is the timeline.
- **Declared OWASP A04 deviation, dated 2026-11-25, review due 2027-05-19:**
  lead email/phone/address remain plaintext because the list search, duplicate
  detection and export rely on exact and `LIKE` predicates. Field-level
  encryption needs a blind-index design for those columns and a migration plan
  for the existing rows; it is tracked as a cross-cutting change, not a module
  shortcut. PII is excluded from `LogsActivity` and from application logs.
- Export transformers live in `Application/Exports/`; the writer/PDF
  mechanics stay behind `Shared\Domain\Ports\ExportPort`.
- US federal holidays are computed in `UsFederalHolidayProvider` from Carbon + OPM
  observed rules (no external holiday package). `lead-appointment:sync-holidays`
  runs yearly on Jan 1 at 00:10 America/Chicago with `withoutOverlapping(60)` and
  `onOneServer()`, materialises the current + next year, and never purges manual
  exceptions. Run it once on deploy if the app is installed mid-year.
- Demote to FLAT when 0/4 triggers hold for two quarters and no test-covered
  invariant remains in `Domain/`. Demoting means moving files, not deleting logic.

## Tests holding the layers up (no-lock rule)

- `Tests/Unit/AvailabilityResolverTest.php` — resolver behaviour with a fake
  `AvailabilityScheduleReader`; `tests/Architecture/ModuleComplexityTest.php`
  enforces the `Domain/` purity and inward-dependency rules.
- `Tests/Unit/BookingGuardTest.php` — 7 h + 1 h travel, one-per-day cap, past/closed.
- `Tests/Unit/ScheduleAppointmentActionTest.php` — DB-free with mocked ports.
- `Tests/Feature/PublicLeadApiTest.php` — 201/422/429 + duplicate linking.
- `Tests/Feature/AppointmentSchedulingTest.php` — 401/403/422 + happy path.
- `Tests/Feature/LeadPagesTest.php` — Inertia lead screens: index/board/create/
  edit/show props, Inertia create, legal and illegal stage moves.
- `Tests/Feature/AvailabilityPageTest.php` — Inertia availability screens: month
  window props, filters (source/state/record state), page writes, duplicate-date
  error, export contract and the JSON/page separation.
- `Tests/Feature/SyncHolidaysCommandTest.php` — 11 federal holidays, idempotent.
- `Tests/Unit/SyncAppointmentToGoogleCalendarTest.php` — listener orchestration.
- `tests/Architecture/ModuleComplexityTest.php` — mode, purity and no `Resources/`.
