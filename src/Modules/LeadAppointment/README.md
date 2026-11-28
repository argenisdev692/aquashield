# LeadAppointment

> Lead intake → qualification → availability → inspection → calendar →
> customer PDF/email. One bounded context for AquaShield's roofing and water
> mitigation operation across Houston, Dallas and Florida.

**Status:** backend complete; lead CRUD, pipeline board, detail screens, the
appointment screens and the real dashboard (period-scoped lead columns, KPI
sparklines and appointments) are live. The availability screen set (month grid,
weekly hours, day exceptions and their CSV/XLSX/PDF export) is live too.
**Last verified:** 2026-11-27 · full backend suite 955 tests / 4,392 assertions.
`doctor` passes on 2026-11-25 (25 diagnostics, 0 failed, 1 skipped); the known
Windows autoload timeout is excluded by the gate (see §11).

This document is the review entry point. It states what the module owns, what it
deliberately does not, its size budget, and how much a change costs. Read this
before opening the 180+ files.

---

## 1. What it owns

| Capability         | In this module                                   | Notes                                                   |
| ------------------ | ------------------------------------------------ | ------------------------------------------------------- |
| Public lead intake | `POST /api/leads`                                | Honeypot, content scoring, dedupe, TCPA consent         |
| Meta lead ads      | `GET/POST /api/meta/webhook`                     | HMAC, idempotent `lead_ingestion_events`                |
| Sales pipeline     | Leads + `lead_activities`                        | Explicit status transition map; table + kanban screens  |
| Dashboard          | `LeadRepository::board` + period KPIs            | Board, KPIs and table share one URL period/scope        |
| Availability       | `availability_rules` + `availability_exceptions` | Company exception > company rule > closed               |
| Scheduling         | `appointments`                                   | UTC `timestamptz` + local `timezone`, half-open overlap |
| Calendar           | `GET /appointments/calendar`                     | Read model, no public slot picker                       |
| Google Calendar    | Shared company calendar                          | Queued listener + circuit breaker                       |
| Exports            | CSV / XLSX / PDF                                 | Same filters and permission as the list                 |
| Reports            | Lead-source conversion                           | Same filter contract                                    |
| Holidays           | `lead-appointment:sync-holidays`                 | Carbon + OPM observed rules, no package                 |
| Corporate output   | Email + PDF Blade templates                      | Palette and layout in `resources/views`                 |

## 2. Surfaces

| Surface           | Count | Auth                                 |
| ----------------- | ----- | ------------------------------------ |
| Public API routes | 4     | None; throttled, honeypot, HMAC      |
| Admin web routes  | 57    | `auth` + `verified` + `permission:*` |
| Admin API v1      | 44    | `auth:sanctum` + `permission:*`      |
| Console commands  | 1     | Scheduler                            |
| Database tables   | 7     | —                                    |
| Domain events     | 8     | —                                    |
| Listeners         | 6     | Mail and Google Calendar adapters    |

The web surface is now dual-transport: `GET /leads` and `GET /leads/{uuid}`
answer JSON for the DataTable/API and Inertia for the React pages, while
`/leads/board`, `/leads/create` and `/leads/{uuid}/edit` are page-only. Instead
of a second permission source, `Routes/web.php` reuses `Routes/admin.php` and
adds only the screens that cannot be shared with `/api/v1`.

The availability screen set is the one surface that keeps the two contracts
apart on purpose. The JSON resource stays at `GET/POST /availability-exceptions`
and `GET/POST /availability-rules`, and the month grid reads
`GET /availability/calendar`; the pages live at `/availability`,
`/availability/create` and `/availability/{uuid}/edit`, and their writes are
Inertia redirects. Both read the same repositories, so the table, the month grid
and the export cannot disagree about a day.

### Admin roles and permissions

The CRUD is permission-gated (Spatie), not role-gated. `RolePermissionSeeder`
grants the LeadAppointment matrix:

| Role             | Leads                | Appointment / availability |
| ---------------- | -------------------- | -------------------------- |
| `SUPER_ADMIN`    | Full                 | Full                       |
| `ADMIN`          | Full                 | Full                       |
| `MANAGER`        | Full                 | Full                       |
| `ADMINISTRATIVE` | View, create, update | —                          |
| `LEAD`           | View, create, update | —                          |

"Full" means view, create, update, delete, restore and bulk delete/restore;
the lead, appointment, availability and source-report surfaces also expose
exports. The availability export is served on both surfaces because
`Routes/admin.php` is reused by `/api/v1`; it carries the same two permission
gates and the export throttle. `LEAD` is not owner-scoped today:
`VIEW_ANY_LEAD` lists every lead. If
that role must see only its own leads, add a `VIEW_OWN_LEAD` permission and an
`owner_id` scope before granting it.

The sidebar reads `authPermissions` from `HandleInertiaRequests` (never the
role name). The Leads row appears only with `VIEW_ANY_LEAD`; the list, board and
detail also re-check `VIEW_LEAD`/`UPDATE_LEAD` on the server, and the create
button needs `CREATE_LEAD`. The board's stage select is rendered only when
`UPDATE_LEAD` is granted, but the write itself is enforced by route middleware.

Scramble documents the public `api/*` surface and the `/api/v1` admin surface.
The web CRUD in `Routes/web.php` is session-authenticated and is not duplicated
in `api.json`; `/api/v1` is the bearer-token surface.

### React Native / external clients

The admin surface is available twice from one definition (`Routes/admin.php`):

- `Routes/web.php` — session + `verified`.
- `Routes/api_v1.php` — `auth:sanctum` + `throttle:api`.

Login and identity:

- `POST /api/auth/login`
- `GET /api/auth/me` (includes `permissions`)
- `POST /api/auth/refresh`
- `POST /api/auth/logout`

Management:

- `GET|POST /api/v1/leads`, `GET|PUT|DELETE /api/v1/leads/{uuid}`
- `POST /api/v1/leads/{uuid}/status`, `/activities`, `/merge`
- `GET|POST /api/v1/appointments`, `POST /api/v1/appointments/{uuid}/confirm`,
  `/reschedule`, `/cancel`, `/complete`
- `/api/v1/availability-*`, `/api/v1/reports/*`

The app authorizes by `user.permissions` (`DELETE_LEAD`, `CREATE_APPOINTMENT`,
etc.), never by role name. The server re-checks the same permission.

## 3. Why PROGRESSIVE

PROGRESSIVE is justified by the triggers in
`.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`:

- **≥3 models with business rules:** `Lead`, `Appointment`, `AvailabilityRule`,
  `AvailabilityException`.
- **Invariants:** no overlapping active appointment for the single crew;
  company availability precedence; explicit status transitions; duplicate leads
  are linked, never hard-blocked.
- **Tests that swap persistence/external services:** DB-free action tests with
  mocked ports; Google Calendar, Meta Graph and the holiday provider substituted.
- **Extraction roadmap:** none. One bounded context, one company.

**OCP bet:** made 2026-11-19, review due 2027-05-19. It was over-engineering if
no second consumer of the scheduling ports appears and no invariant beyond this
document is test-covered by then. The de-escalation condition is in
`ARCHITECTURE.md`.

## 4. Complexity budget

Measured on 2026-11-25, excluding `vendor/` and `node_modules/`:

| Measure                 |                                              Value |
| ----------------------- | -------------------------------------------------: |
| PHP files in the module |                                            **218** |
| Production PHP files    |                                            **187** |
| Production PHP lines    |                                          **9,612** |
| Test files              |                                             **29** |
| Test lines              |                                          **2,851** |
| Average production file |                                      **~51 lines** |
| Actions                 |                                             **11** |
| DTOs                    |                                             **36** |
| FormRequests            |                                             **13** |
| Ports                   |                                             **11** |
| Query objects           | **2** (`CalendarFeedQuery`, `DashboardStatsQuery`) |
| Migrations              |       **15** (7 create + 8 alters and lock tables) |

The file count is high; the line count is not. This is a wide, shallow module:
many small classes with one reason to change, not a few large classes with many.
The real cost is **change amplification**, not volume.

## 5. Change amplification

| Change                         | Files touched | Why                                                          |
| ------------------------------ | ------------: | ------------------------------------------------------------ |
| Add a lead field               |            ~9 | migration, model, DTO, 3 requests, repository, factory, test |
| Add an appointment state       |            ~6 | enum, transition map, action, request, test, calendar        |
| Add an integration             |            ~4 | port, adapter, provider binding, listener/job                |
| Add a CRUD screen              |            ~5 | controller, request, DTO, route, test                        |
| Change availability precedence |         **1** | `AvailabilityResolver` + its unit test                       |

That last row is the point of the architecture: the hardest rule in the domain is
isolated in one file with a DB-free test. The first row is the cost of the DTO /
request / repository ceremony; it is mechanical, not clever.

## 6. Legacy table mapping

`MODULO-LEAD-APPOINMENT/TABLA-APPOINMENT-AQUASHIELD` mixed the lead and its
inspection in one table. The normalized schema keeps the lead and its requested
inspection on `leads`; the confirmed visit is an `appointment`.

| Legacy column                                      | New location                                     | Disposition                                                                                  |
| -------------------------------------------------- | ------------------------------------------------ | -------------------------------------------------------------------------------------------- |
| `first_name`, `last_name`                          | `leads.first_name`, `leads.last_name`            | Same                                                                                         |
| `phone`, `email`                                   | `leads.phone`, `leads.email`                     | Normalized (E.164, lowercase)                                                                |
| `address`, `address_2`, `city`, `state`, `country` | `leads.*`                                        | Same                                                                                         |
| `zipcode`                                          | `leads.zip_code`                                 | Renamed; `zipcode` accepted as input alias                                                   |
| `insurance_property`                               | `leads.insurance_property`                       | Same name, added 2026-11-20                                                                  |
| `message`                                          | `leads.message`                                  | Visitor message, added 2026-11-20                                                            |
| `sms_consent`                                      | `leads.sms_consent` + consent audit columns      | Same, plus timestamp/IP/version                                                              |
| `registration_date`                                | `leads.created_at`                               | Derived; not duplicated                                                                      |
| `inspection_date`, `inspection_time`               | `leads.inspection_date`, `leads.inspection_time` | Operator records them when the lead is called; a confirmed appointment wins in `LeadData`    |
| `inspection_status`                                | `leads.inspection_status`                        | `pending`, `confirmed`, `completed`, `declined`; mapped from the appointment when one exists |
| `status_lead`                                      | `leads.status`                                   | Enum: `new`, `contacted`, `qualified`, `inspection_scheduled`, `won`, `lost`, `nurture`      |
| `lead_source`                                      | `leads.source`                                   | Enum: `website`, `meta`, `phone`, `referral`, `manual`, `retell_ai`, `other`                 |
| `follow_up_calls` (JSON)                           | `lead_activities` (`type = call`)                | Normalized timeline; not duplicated as JSON                                                  |
| `notes`                                            | `leads.notes`                                    | Same                                                                                         |
| `owner` (string)                                   | `leads.owner_id` → `users`                       | FK; `owner_name` exposed read-only                                                           |
| `damage_detail`                                    | `leads.damage_description`                       | Renamed; `damage_detail` accepted as input alias                                             |
| `intent_to_claim`                                  | `leads.intent_to_claim`                          | Same, added 2026-11-20                                                                       |
| `follow_up_date`                                   | `leads.follow_up_date`                           | Same, added 2026-11-20                                                                       |
| `additional_note`                                  | `leads.additional_note`                          | Same, added 2026-11-20                                                                       |
| `latitude`, `longitude`                            | `leads.latitude`, `leads.longitude`              | Same, added 2026-11-20                                                                       |

Read model additions on `LeadData`: `registration_date`, `inspection_date`,
`inspection_time`, `inspection_status`. The inspection fields read the lead
columns; when a non-cancelled appointment exists, the confirmed appointment wins.
`zipcode` and `damage_detail` are transport aliases handled by
`Http/Requests/Concerns/NormalizesLegacyLeadInput`; the canonical API names stay
`zip_code` and `damage_description`.

### Creator attribution

`leads.created_by` and `appointments.created_by` are nullable foreign keys to
`users`, set from the authenticated session (`$request->user()?->id`) on admin
writes. Public website, Meta ingestion and seeded imports leave them null. This
is distinct from `leads.owner_id`, which is the assigned owner. Both columns are
nullable so a user deletion never removes the lead or the appointment.

**What is not accepted through the lead endpoint:** `follow_up_calls` (use
`POST /leads/{uuid}/activities`) and `registration_date` (set `created_at` on
import). `inspection_date`, `inspection_time` and `inspection_status` are accepted
on the status endpoint when the operator marks the lead as called.

### Pipeline and inspection date

`POST /leads/{uuid}/status` accepts the legacy vocabulary and normalizes it:

| Legacy     | Current     | Use                            |
| ---------- | ----------- | ------------------------------ |
| `New`      | `new`       | Just captured                  |
| `Called`   | `contacted` | Operator spoke to the customer |
| `Pending`  | `nurture`   | Follow up later                |
| `Declined` | `lost`      | Customer declined              |

When the customer agrees to an inspection, the operator sends `status: contacted`
plus `inspection_date` and, optionally, `inspection_time`. The lead stays
`contacted`; it does not become `inspection_scheduled` just because a date was
recorded. The date is the operator's requested/recorded inspection.

Creating the actual visit through `POST /appointments` moves the lead to
`inspection_scheduled` and creates the appointment in `scheduled`. From there:
`confirm` → `completed`. The estimate signature is **not** part of this module;
the future Claim + Scope Sheet modules own it. That is why `estimate_sent` does
not exist.

Scheduling is deliberately conservative: **one crew, one 7 h inspection plus
1 h travel per day**. The block is `[starts_at, ends_at + 60 min]`; a 16:00
start would end at 23:00 and is rejected by the 08:00–17:00 window before the
daily cap is even checked. The values live in
`config/lead-appointment.php` (`inspection_duration_minutes`,
`travel_buffer_minutes`, `daily_inspection_capacity`).

### Public form: region and anti-spam

- `region` is nullable while the Google Maps autocomplete contract is settled.
  A lead without a metro is accepted; scheduling falls back to the request
  region, then `config('lead-appointment.default_region')`. Send `region` when
  the page knows the market.
- `POST /api/leads` rejects RFC 2606/6761 placeholders (`example.com`,
  `.test`, `.invalid`, `.localhost`) and the configured placeholder/disposable
  domains. `company.com`, `company`, `mailinator.com` and similar are blocked.
- Role mailboxes (`info@`, `sales@`) and suspicious local parts are scored, not
  rejected, so a legitimate business inbox can be reviewed.
- `consent_text_version` falls back to `SMS_CONSENT_VERSION` when the form omits
  it. Send the hidden version when the disclosure text is versioned.

## 7. No service catalog in this phase

The service catalog was removed on **2026-11-23**. AquaShield assigns work
services only after the insurance claim is signed and the scope sheet exists;
until then the lead is a roofing/water-mitigation inspection, not a priced
service. Keeping a `services` table here created a second, premature source of
truth for the downstream Claim + Scope Sheet flow.

What changed:

- `services` and `service_technician` tables, `ServiceController`, the public
  `GET /api/services` feed, `ServiceData`, `ServiceKind`, the model, factory and
  seeder rows are gone.
- `leads.service_id` and `appointments.service_id` are gone; `LeadData`,
  `AppointmentData` and the export transformers no longer expose a service.
- Appointment duration now comes from the request or
  `config('lead-appointment.inspection_duration_minutes')`, not from a catalog row.
- The parallel `technicians` table was removed on the same date. The inspector
  is a `User` with the `TECHNICAL_SERVICES` role when Claim + Scope Sheet assigns
  one; there is no second identity table to keep in sync.

When the Claim + Scope Sheet modules land, they can introduce a service catalog
with the fields that flow actually needs (code, unit, pricing) without this
module having guessed them first.

## 8. Meetings

Meetings were **not** ported. In the reference implementation `Meeting` is a
separate aggregate: one staff organizer, polymorphic attendees (user / lead /
contact), Google Calendar event and Google Meet link. It is internal team
scheduling, not a customer inspection.

What exists in this module today:

- `AppointmentKind::AdjusterMeeting` — an adjuster visit is still an appointment.
- `LeadActivityType::Meeting` — a logged touchpoint is still an activity.

If AquaShield needs internal meetings, port `Meeting` as its own module with its
own spec and tables. Do not add `meetings` to `LeadAppointment`; it would import
a second aggregate and a second calendar sync path into an already complete
bounded context.

## 9. Lean mode (~130 files)

If this module ever needs to be a smaller showcase, cut ceremony without touching
a business rule:

| Cut                                                       | From |  To |
| --------------------------------------------------------- | ---: | --: |
| FormRequests (merge store/update with `sometimes`)        |   16 |   9 |
| DTOs (merge `*Data` / `Save*Data` pairs)                  |   28 |  20 |
| Actions (one `TransitionAppointmentAction`)               |   10 |   7 |
| Mailables + listeners (2 notifications, 2 listeners)      |   11 |   4 |
| Factories (only those the tests use)                      |    7 |   3 |
| Domain exceptions (one `SchedulingException` with a code) |    9 |   5 |
| Calendar DTOs                                             |    2 |   1 |
| Availability ports                                        |    2 |   1 |

Result: ~130 files, same `AvailabilityResolver`, same `BookingGuard`, same
overlap query, same tests. The lean mode is a dial, not a rewrite.

## 10. Out of scope

- A public real-time slot picker; staff scheduling remains the only booking
  surface. The Inertia availability screen and `/api/v1` JSON surface are live.
- A service catalog and technician assignment to jobs (they belong to the
  Claim + Scope Sheet flow, after the inspection).
- PostgreSQL concurrency proof for `createWithLock` remains a follow-up phase.
- Per-technician Google OAuth; one shared company calendar.
- Public real-time slot picker; scheduling is staff-driven and request-only.
- Meetings, Jobs, Customers, multi-property accounts.
- Event sourcing; `lead_activities` is the timeline, not a full event store.
- `spatie/holidays`; the holiday provider is self-contained.

## 11. Gate evidence

```text
npm run module:codegen   codegen + fix + types + build green
Pint --blade --test      clean
PHPStan level 5          0 errors
tsc --noEmit             clean
Vite build               green
Full backend suite     955 tests / 4,392 assertions
Architecture tests       51 tests / 274 assertions
SavedViews tests         10 tests / 67 assertions
Composer audit           clean
doctor                   green: 25 diagnostics, 0 failed, 1 skipped
```

The last full Pest run was 955 tests / 4,392 assertions (`phpunit.xml` sets
`memory_limit=1G` for the test process). `doctor` passes on 2026-11-27; the Windows
\`Composer autoload works\` timeout is documented in `.opencode/rules/rules.md`
and is not
a module finding and must not be silenced by changing the project.

## 12. Read these seven files first

1. `Domain/Services/AvailabilityResolver.php` — the precedence engine.
2. `Domain/Services/BookingGuard.php` — past/closed/overlap/capacity invariants.
3. `Application/Actions/ScheduleAppointmentAction.php` — the core use case.
4. `Infrastructure/Persistence/Eloquent/Repositories/EloquentAppointmentRepository.php`
   — `createWithLock`, overlap query, mapping.
5. `Http/Controllers/Api/PublicLeadController.php` — public intake and throttling.
6. `Infrastructure/GoogleCalendar/` — adapter, circuit breaker, queued listener.
7. `resources/js/Pages/Leads/` — the live table, board and lead file; the board
   receives server-grouped columns and posts a stage change, never a client sort.

If those six are coherent, the other 180 files are mechanical.
