# Backups — operations runbook

Database-only backups (`backup:run --only-db`) for AquaShield. The archive is a
zip produced by `spatie/laravel-backup` 10; this module is the panel over it.

## HTTP surface

| Method   | Path                       | Permission             | What it does                               |
| -------- | -------------------------- | ---------------------- | ------------------------------------------ |
| `GET`    | `/backups`                 | `VIEW_ANY_BACKUPS`     | List + search + filter + sort + paginate   |
| `GET`    | `/backups/activity`        | `VIEW_ANY_BACKUPS`     | Read-only audit trail of backup operations |
| `POST`   | `/backups/run`             | `CREATE_BACKUPS`       | Queue a manual database dump (202)         |
| `GET`    | `/backups/{uuid}/download` | `DOWNLOAD_BACKUPS`     | Stream the archive (audited, 10/min)       |
| `DELETE` | `/backups/{uuid}`          | `DELETE_BACKUPS`       | Move one archive to quarantine             |
| `PATCH`  | `/backups/{uuid}/restore`  | `RESTORE_BACKUPS`      | Move a quarantined archive back            |
| `POST`   | `/backups/bulk-delete`     | `BULK_DELETE_BACKUPS`  | Quarantine up to 500 archives              |
| `POST`   | `/backups/bulk-restore`    | `BULK_RESTORE_BACKUPS` | Restore up to 500 archives                 |
| `GET`    | `/backups/export/{format}` | `EXPORT_BACKUPS`       | CSV / XLSX / PDF, same filters as the list |

All routes are under `web` (session + CSRF) + `auth` + `verified`. Responses are
JSON; the Inertia screen is a separate `frontend-new` task.

A read-only REST surface also exists under `/api` (Sanctum bearer token,
Scramble-documented in `api.json`): `GET /api/backups`,
`GET /api/backups/{uuid}`, `GET /api/backups/{uuid}/download`. Destructive
verbs stay on the session + CSRF web routes on purpose — a stolen bearer token
must not be able to quarantine the only recovery point.

## Retention (two-stage)

```
active      backup:run --only-db                 02:30 daily
            backup:clean                         03:30 daily
              keep all for 7 days
              then 1/day for 16 days
              then 1/week for 8 weeks
              then 1/month for 4 months
              then 1/year for 2 years
              and never the newest archive
quarantine  panel delete moves the file here
            backups:purge-quarantine             04:00 daily
              permanently deletes after 30 days
audit       activitylog:clean --force            04:30 on the 1st
              default 2555 days = 7 years (SOX/GLBA 7y, HIPAA 6y, PCI 12m)
health      backup:monitor                       07:00 daily
```

The quarantine window is `backup.quarantine.retain_days`
(`BACKUP_QUARANTINE_RETAIN_DAYS`). The package retention is env-driven
(`BACKUP_KEEP_*`, `BACKUP_MAX_STORAGE_MB`) so a policy change is a config
change, not a deploy.

**Why two stages:** an authenticated web request must not be able to destroy the
only recoverable dump. Delete is reversible for 30 days; the irreversible step
is a console command with `--dry-run`, a window and an audit entry.

## Railway / production

- **`pg_dump` is in the image, not an env path.** The Dockerfile installs
  `postgresql-client-17` because Supabase runs PostgreSQL 17.6; Ubuntu 24.04's
  default client is 16 and refuses a newer server. `DB_DUMP_BINARY_PATH` is the
  **Windows local** path (`C:/Program Files/PostgreSQL/17/bin`). On Railway
  leave it unset (the client is on PATH) or set `/usr/lib/postgresql/17/bin`.
  Never paste the Windows path into Railway.
- **Supervisord runs three processes:** `app`, `worker` (`queue:work`) and
  `scheduler` (`schedule:work`). `schedule:work` is the only thing that runs the
  cron entries; `queue:work` is the only thing that processes the manual
  "Run backup" job, invitations and password resets. They are not the same
  process.
- **Use the direct Supabase connection or the session pooler (port 5432)** for
  `pg_dump`, not the transaction pooler (6543). The dump is a session-level
  operation.
- If you replace `schedule:work` with a Railway cron running
  `php artisan schedule:run` every minute, remove the `scheduler` program.
  Do not run both.
- `onOneServer()` and `withoutOverlapping()` need a shared cache store
  (redis or database), not `file`/`array`, once more than one replica runs.
- Verify after deploy in the container: `pg_dump --version` (must be 17.x),
  `php artisan schedule:list`, then one manual
  `php artisan backup:run --only-db --disable-notifications` and confirm the
  archive on `/backups`.

## Restore (out of band, deliberately)

This module does **not** expose a database restore endpoint. Restoring a dump
means stopping writes, dropping/recreating the schema and replaying the dump;
that needs maintenance mode and an operator watching it. Procedure:

1. Pick the archive in the panel and download it (or fetch it from the disk).
2. Decrypt it if `BACKUP_ARCHIVE_PASSWORD` is set.
3. Put the app in maintenance mode: `php artisan down --secret="…"`.
4. Restore with the database engine's client (`mysql < dump.sql`,
   `pg_restore -d …`, or replace the SQLite file).
5. Run `php artisan migrate --force` if the dump predates a migration.
6. `php artisan up`, then verify the newest archive and `backup:monitor`.

## Security notes

- Archive encryption is on when `BACKUP_ARCHIVE_PASSWORD` is set; set it in
  production (the dump contains every claim and customer record).
- **3-2-1-1-0 is not satisfied by this config yet.** `backup.backup.destination.disks`
  has one disk, and quarantine is on that same disk. The 30-day quarantine
  protects against operator error, **not** against ransomware or a stolen
  credential. Add a second destination (S3/R2 with Object Lock or bucket
  versioning) for the offsite immutable copy, and run restore drills — the
  "0 errors" leg. Until then this is a single-copy backup.
- Downloads are permission-gated, throttled and audited. The storage path is
  never exposed; the response carries only the filename.
- The audit endpoint is read-only and applies a property allowlist. An audit
  trail the panel can rewrite is not an audit trail (OWASP A09).
- `SUPER_ADMIN` receives the full family. `ADMIN` receives `VIEW_ANY`, `VIEW`
  and `EXPORT` on backups and the activity log — enough to inspect and export,
  not enough to download a full database copy, quarantine/restore an archive or
  start a dump. Grant `DOWNLOAD_BACKUPS` or `CREATE_BACKUPS` explicitly if an
  operator genuinely needs it: a backup download is a full copy of the database.
- The disk holding backups must be private. `local` is under
  `storage/app/private`; if a cloud disk is added, keep the bucket private and
  use the signed/streamed route, never a public URL.

## Verify

```bash
php artisan backups:purge-quarantine --dry-run
php artisan schedule:list
php artisan route:list --path=backups
php artisan test src/Modules/Backups/Tests
```
