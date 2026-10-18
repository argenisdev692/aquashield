#!/usr/bin/env node
/**
 * module-make.mjs — JSON → FLAT simple-CRUD scaffolding.
 *
 * SIMPLE CRUD ONLY (3–8 fields, no state machines, no files/exports/queues
 * unless asked). Anything complex belongs to `/backend-new` (PROGRESSIVE) and
 * needs human preparation — this script refuses to fake it.
 *
 * Usage:
 *   node scripts/module-make.mjs <spec.json> [--dry-run] [--force]
 *   npm run module:make -- <spec.json>
 *
 * Spec example: stubs/module/module.spec.example.json
 * (usually written by the agent during the SSD PLAN phase, section 4).
 *
 * What it generates (backend only — frontend stays manual via /frontend-new):
 *   src/Modules/{Module}/
 *     ARCHITECTURE.md (mode: FLAT, from stubs/architecture/ARCHITECTURE.FLAT.md)
 *     Models/{Entity}.php (+ user() belongsTo when userOwned)
 *     Data/{Entity}Data.php + Data/{Entity}FilterData.php (Spatie Data)
 *     Http/Controllers/{Entity}Controller.php (fused web+JSON)
 *     Http/Requests/Store|Update[|BulkDelete|BulkRestore]{Entity}Request.php
 *     Routes/web.php (+ api.php with --api)
 *     Providers/{Module}ServiceProvider.php
 *     Tests/Feature/{Entity}CrudTest.php (Pest, green on sqlite :memory:)
 *   database/migrations/YYYY_MM_DD_HHMMSS_create_{table}_table.php
 *   database/factories/{Entity}Factory.php
 * Patches (idempotent, guarded — skipped if already present):
 *   App\Models\User gets the inverse hasMany when userOwned.
 *   bootstrap/providers.php gets the module provider.
 * Prints (never writes): permission seeder snippet + next steps.
 *
 * No dependencies — Node builtins only.
 */

import { execSync } from 'node:child_process';
import {
    existsSync,
    mkdirSync,
    readFileSync,
    writeFileSync,
    copyFileSync,
    readdirSync,
} from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const argv = process.argv.slice(2);
const DRY = argv.includes('--dry-run');
const FORCE = argv.includes('--force');
const SKIP_EXISTING = argv.includes('--skip-existing');
const fileArg = argv.find((a) => !a.startsWith('--'));

// Batch mode: no file arg → run every runnable spec in specs/.
// YOU drop attribute JSONs there (AI or manual), npm run does the rest.
if (!fileArg) {
    const dir = join(ROOT, 'specs');
    const files = existsSync(dir)
        ? readdirSync(dir)
              .filter((f) => f.endsWith('.json') && !f.startsWith('_') && !f.startsWith('.'))
              .sort()
        : [];

    if (files.length === 0) {
        console.error('module-make: specs/ has no runnable specs.');
        console.error(
            '  Drop one per module in specs/{name}.spec.json (see specs/_example.spec.json),',
        );
        console.error(
            '  or run a single file: npm run module:make -- path/to/spec.json [--dry-run]',
        );
        process.exit(1);
    }

    console.log(
        `module-make: batch — ${files.length} spec(s): ${files.join(', ')}${DRY ? ' (dry-run)' : ''}\n`,
    );

    let failed = 0;
    for (const f of files) {
        const flags = [DRY ? '--dry-run' : '', FORCE ? '--force' : '--skip-existing']
            .filter(Boolean)
            .join(' ');
        try {
            execSync(`node scripts/module-make.mjs "${join(dir, f)}" ${flags}`, {
                cwd: ROOT,
                stdio: 'inherit',
            });
        } catch {
            failed++;
            console.error(`module-make: FAILED — ${f} (continuing with the rest)`);
        }
    }

    if (failed > 0) {
        console.error(`module-make: batch done with ${failed} failure(s).`);
        process.exit(1);
    }

    console.log('\nmodule-make: batch complete.');
    process.exit(0);
}

const specPath = resolve(ROOT, fileArg);

if (!existsSync(specPath)) {
    console.error('Usage: node scripts/module-make.mjs [<spec.json>] [--dry-run] [--force]');
    console.error('  No arg  → batch every specs/*.spec.json  (npm run module:make)');
    console.error('  One arg → single spec (AI flow: npm run module:make -- module.spec.json)');
    process.exit(1);
}

/* ------------------------------------------------------------------ spec */

const spec = JSON.parse(readFileSync(specPath, 'utf8'));

const fail = (msg) => {
    console.error(`module-make: ERROR — ${msg}`);
    process.exit(1);
};
const warn = (msg) => console.warn(`module-make: WARN — ${msg}`);

/*
 * Lifespan — proportionality, not ceremony (PRINCIPLES/SKILL.md §6).
 *
 * The principles scale with how long the code lives. This generator builds a MODULE,
 * and a module is the `module` lifespan by definition: it gets Data classes, a
 * FilterData, owner scope, 401/403/422, and the bulk pair.
 *
 * So the flag is a declaration, not a switch. Writing "lifespan": "module" in the
 * spec is an explicit act of choosing that ceremony — which is the whole point.
 * Choosing "one-shot" is refused here, at the earliest possible moment, because a
 * one-line maintenance command generated with a Data class, a policy and a 403
 * suite is exactly the speculative build YAGNI forbids: each piece individually
 * defensible, collectively a folder nobody asked for.
 */
const LIFESPANS = ['one-shot', 'module'];
const lifespan = spec.lifespan ?? 'module';

if (!LIFESPANS.includes(lifespan)) {
    fail(`Unknown lifespan "${lifespan}". Supported: ${LIFESPANS.join(', ')}.`);
}

if (lifespan === 'one-shot') {
    fail(
        'lifespan: one-shot is not a module, so this generator does not apply.\n' +
            '  A one-shot artifact is an Artisan command, a migration, or a seeder.\n' +
            '  It still gets: declare(strict_types=1), explicit types, a Pest test, and Pint.\n' +
            '  It does NOT get: Data classes, FilterData, exports, policies, a service\n' +
            '  provider, ownership scoping, or the 401/403/422 matrix. Those are the module\n' +
            '  lifespan, and they are what make a module cost what it costs.\n' +
            '  If this thing will live for months and be read by someone else, declare\n' +
            '  lifespan: module and put it back through here.',
    );
}

const TYPES = new Set([
    'string',
    'text',
    'integer',
    'boolean',
    'date',
    'datetime',
    'decimal',
    'uuid',
    'json',
    'foreign',
]);
const PHP_TYPES = {
    string: 'string',
    text: 'string',
    integer: 'int',
    boolean: 'bool',
    date: '?string',
    datetime: '?string',
    decimal: 'float',
    uuid: 'string',
    json: 'array',
    foreign: 'int',
};

for (const key of ['module', 'entity', 'table', 'route']) {
    if (!spec[key] || typeof spec[key] !== 'string') fail(`spec.${key} is required (string).`);
}
if (!/^[A-Z][A-Za-z0-9]*$/.test(spec.module)) fail('spec.module must be PascalCase.');
if (!/^[A-Z][A-Za-z0-9]*$/.test(spec.entity)) fail('spec.entity must be PascalCase.');
if (!Array.isArray(spec.fields) || spec.fields.length === 0)
    fail('spec.fields must be a non-empty array.');
if (spec.fields.length > 8) {
    warn(
        `${spec.fields.length} fields > 8 — simple-CRUD ceiling. Split the module or promote to /backend-new. Continuing anyway.`,
    );
}
for (const f of spec.fields) {
    if (!f.name || !TYPES.has(f.type))
        fail(`bad field: ${JSON.stringify(f)} (type must be one of ${[...TYPES].join('|')}).`);
    if (f.type === 'foreign' && !f.references)
        fail(`foreign field '${f.name}' needs "references": "<table>".`);
    if (f.name === 'uuid' || f.name === 'id') fail(`'${f.name}' is reserved by the scaffold.`);
}

const Module = spec.module;
const Entity = spec.entity;
const entities = spec.entities ?? `${Entity}s`;
const table = spec.table;
const route = spec.route;
const PERM = (spec.permissions ?? table).toUpperCase().replace(/-/g, '_');
const userOwned = spec.userOwned !== false; // default true: every row belongs to a user
const softDeletes = spec.softDeletes !== false;
const bulk = spec.bulk !== false;
const api = spec.api === true;

/* Export formats: all three are served by Shared\Domain\Ports\ExportPort.
   `tabular()` picks its driver from the extension (csv, xlsx); `pdf()` delegates
   to dompdf. An unknown format is a hard error, not a silent skip. */
const EXPORT_FORMATS = ['csv', 'xlsx', 'pdf'];
const requestedExports = Array.isArray(spec.exports) ? spec.exports : [];
const unknownExports = requestedExports.filter((e) => !EXPORT_FORMATS.includes(e));

if (unknownExports.length > 0) {
    fail(
        `Unknown export format(s): ${unknownExports.join(', ')}. Supported: ${EXPORT_FORMATS.join(', ')}.`,
    );
}

const exports = [...new Set(requestedExports)];
if (Array.isArray(spec.exports) && exports.length !== spec.exports.length) {
    warn(
        `only "csv" exports are supported by the generator (got: ${spec.exports.join(', ')}). Unsupported formats skipped.`,
    );
}
const PERMS = ['VIEW', 'CREATE', 'UPDATE', 'DELETE']
    .concat(softDeletes ? ['RESTORE'] : [])
    .concat(bulk ? [] : []) // bulk reuses DELETE_/RESTORE_, no extra permission
    .concat(exports.length > 0 ? ['EXPORT'] : [])
    .map((p) => `${p}_${PERM}`);
const permsPhp = PERMS.map((p) => `'${p}'`).join(', ');
const pluralVar = entities.charAt(0).toLowerCase() + entities.slice(1);
const singleVar = Entity.charAt(0).toLowerCase() + Entity.slice(1);
const hasManyName = pluralVar; // User->{plural}()
/*
 * Ownership-aware load. `user_id` is written on create but nothing else ever read
 * it, so every generated userOwned module was an IDOR: any authorised user could
 * read, update or delete any other user's row by uuid. These two expressions are
 * the single place that closes it — every read and every write uses one of them.
 */
const loadOwned = userOwned
    ? `${Entity}::assertOwned(${Entity}::query()->where('uuid', $uuid)->first(), $request->user()?->getKey())`
    : `${Entity}::query()->where('uuid', $uuid)->firstOrFail()`;
const loadOwnedTrashed = userOwned
    ? `${Entity}::assertOwned(${Entity}::withTrashed()->where('uuid', $uuid)->first(), $request->user()?->getKey())`
    : `${Entity}::withTrashed()->where('uuid', $uuid)->firstOrFail()`;
const ownedScope = userOwned ? '->ownedBy($request->user()?->getKey())' : '';
/* Bulk: every requested uuid must be owned, or the whole request is refused.
   The trashed variant counts soft-deleted rows too — a restore request runs
   against rows that are already deleted, so scoping without withTrashed() there
   would 403 the owner of their own row. */
const assertAllOwned = (withTrashed) =>
    userOwned
        ? `        abort_unless(
            ${Entity}::query()${withTrashed ? '->withTrashed()' : ''}->ownedBy($request->user()?->getKey())->whereIn('uuid', $uuids)->count() === count($uuids),
            403,
        );

`
        : '';
const dataFields = spec.fields.filter((f) => f.type !== 'foreign');
const foreignFields = spec.fields.filter((f) => f.type === 'foreign');
const searchable = spec.fields.filter((f) => f.searchable).map((f) => f.name);
const searchCols = (
    searchable.length > 0
        ? searchable
        : dataFields.filter((f) => ['string', 'text'].includes(f.type)).map((f) => f.name)
).slice(0, 4);
const sortable = ['created_at', 'updated_at', ...spec.fields.map((f) => f.name)].join("', '");

/* ------------------------------------------------------------ field maps */

const migCol = (f) => {
    const n = f.nullable || f.required === false ? '->nullable()' : '';
    const d = f.default !== undefined ? `->default(${JSON.stringify(f.default)})` : '';
    switch (f.type) {
        case 'string':
            return `$table->string('${f.name}'${f.length ? `, ${f.length}` : ''})${n}${d};`;
        case 'text':
            return `$table->text('${f.name}')${n}${d};`;
        case 'integer':
            return `$table->integer('${f.name}')${n}${d};`;
        case 'boolean':
            return `$table->boolean('${f.name}')${n}${d};`;
        case 'date':
            return `$table->date('${f.name}')${n};`;
        case 'datetime':
            return `$table->dateTime('${f.name}')${n};`;
        case 'decimal':
            return `$table->decimal('${f.name}', ${f.precision ?? 10}, ${f.scale ?? 2})${n}${d};`;
        case 'uuid':
            return `$table->uuid('${f.name}')${n};`;
        case 'json':
            return `$table->json('${f.name}')${n};`;
        case 'foreign':
            return `$table->foreignId('${f.name}')${n}->constrained('${f.references}')->cascadeOnDelete();`;
        default:
            return '';
    }
};

/**
 * One rule per array element — Laravel splits string rules on `|`, never on
 * commas, so `'required,string'` would be a single unknown rule (500).
 * The update variant concatenates the current row's uuid for the unique
 * ignore — plain `.` concat inside the array, no eval.
 */
const ruleFor = (f, isUpdate) => {
    const out = [];
    if (isUpdate) out.push('sometimes');
    out.push(f.required === false || f.nullable ? 'nullable' : 'required');
    switch (f.type) {
        case 'string':
        case 'text':
            out.push('string', `max:${f.length ?? 255}`);
            if (f.unique)
                out.push(
                    isUpdate
                        ? `unique:${table},' . $this->route('uuid') . ',uuid`
                        : `unique:${table}`,
                );
            break;
        case 'integer':
            out.push('integer');
            break;
        case 'foreign':
            out.push('integer', `exists:${f.references},id`);
            break;
        case 'boolean':
            out.push('boolean');
            break;
        case 'date':
        case 'datetime':
            out.push('date');
            break;
        case 'decimal':
            out.push('numeric');
            break;
        case 'uuid':
            out.push('uuid');
            break;
        case 'json':
            out.push('array');
            break;
        default:
            break;
    }
    return out;
};

const rulesBlock = (isUpdate) =>
    spec.fields
        .map(
            (f) =>
                `            '${f.name}' => [${ruleFor(f, isUpdate)
                    .map((r) => `'${r}'`)
                    .join(', ')}],`,
        )
        .join('\n');

const castFor = (f) => {
    switch (f.type) {
        case 'integer':
        case 'foreign':
            return `'${f.name}' => 'integer',`;
        case 'boolean':
            return `'${f.name}' => 'boolean',`;
        case 'date':
            return `'${f.name}' => 'date',`;
        case 'datetime':
            return `'${f.name}' => 'datetime',`;
        case 'decimal':
            return `'${f.name}' => 'decimal:2',`;
        case 'json':
            return `'${f.name}' => 'array',`;
        default:
            return null;
    }
};

const dtoProp = (f) => `        public readonly ${PHP_TYPES[f.type]} $${toCamel(f.name)},`;
const toCamel = (s) => s.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
const fillable = [...spec.fields.map((f) => f.name), ...(userOwned ? ['user_id'] : [])];

const HEADER = '<?php\n\ndeclare(strict_types=1);\n';

/* --------------------------------------------------------------- templates */

const files = {};
const MOD = `src/Modules/${Module}`;

files[`${MOD}/ARCHITECTURE.md`] = `# ${Module} — ARCHITECTURE.md (FLAT)

> Generated by \`npm run module:make\` from ${specPath.split(/[\\/]/).pop()}.
> Full rules → \`.opencode/skills/backend-php/SKILL-ARCHITECTURE.md\`.

mode: FLAT

## Why FLAT

Simple CRUD (${spec.fields.length} fields), no invariants beyond validation,
no second consuming module. Generated, not promoted.

## Triggers evaluated (0 of 4)

- [ ] ≥3 models with business rules
- [ ] Invariants (state machine, lifecycle, precondition)
- [ ] Tests that need to swap out persistence or an external service
- [ ] Roadmap: extraction to a microservice

## Accepted debt

None. Promote to PROGRESSIVE when ≥2 triggers fire (then extract the
repository port + re-copy \`stubs/architecture/ARCHITECTURE.PROGRESSIVE.md\`).

## Notes

- Single \`{Entity}Controller\` serves Inertia + JSON (Controller Fusion Rule).
- Response shapes: Spatie \`Data\`, never \`JsonResource\`.
- Data classes live in \`Data/\` (documented here because the FLAT tree does
  not name the folder — the alternative was scattering them across \`Http/\`).
`;

const modelRelations = [
    userOwned
        ? `
    /** @return BelongsTo<\\App\\Models\\User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }`
        : '',
    ...foreignFields.map((f) => {
        const rel = f.name.replace(/_id$/, '');
        const cls = rel.charAt(0).toUpperCase() + rel.slice(1);
        return `
    public function ${rel}(): BelongsTo
    {
        return $this->belongsTo(${cls}::class, '${f.name}');
    }`;
    }),
].join('\n');

files[`${MOD}/Models/${Entity}.php`] = `${HEADER}
namespace Modules\\${Module}\\Models;

use App\\Models\\User;
use Database\\Factories\\${Entity}Factory;
use Illuminate\\Database\\Eloquent\\Attributes\\Fillable;
use Illuminate\\Database\\Eloquent\\Attributes\\Hidden;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Support\\Str;
use Modules\\${Module}\\Data\\${Entity}FilterData;
${softDeletes ? 'use Illuminate\\Database\\Eloquent\\SoftDeletes;\n' : ''}use Spatie\\Activitylog\\Models\\Concerns\\LogsActivity;
use Spatie\\Activitylog\\Support\\LogOptions;

#[Fillable(['${fillable.join("', '")}'])]
#[Hidden(['id'])]
class ${Entity} extends Model
{
    /** @use HasFactory<${Entity}Factory> */
    use HasFactory;
${softDeletes ? '    use SoftDeletes;\n' : ''}    use LogsActivity;

    protected static function newFactory(): ${Entity}Factory
    {
        return ${Entity}Factory::new();
    }

    protected static function booted(): void
    {
        static::creating(static function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    protected function casts(): array
    {
        return [
${spec.fields
    .map(castFor)
    .filter(Boolean)
    .map((c) => `            ${c}`)
    .join('\n')}
        ];
    }
${modelRelations}

    /** @param Builder<$this> $query */
    public function scopeApplyFilters(Builder $query, ${Entity}FilterData $f): Builder
    {
        return $query
${
    searchCols.length > 0
        ? `            ->when($f->search, fn (Builder $q, string $s) => $q->where(function (Builder $q) use ($s): void {
${searchCols.map((c) => `                $q->orWhere('${c}', 'like', "%{$s}%");`).join('\n')}
            }))`
        : ''
}
${
    softDeletes
        ? `            ->when($f->status === 'active', fn (Builder $q) => $q->whereNull($query->qualifyColumn('deleted_at')))
            ->when($f->status === 'deleted', fn (Builder $q) => $q->onlyTrashed())
            // withoutGlobalScope(SoftDeletingScope) is exactly what withTrashed() does,
            // and unlike withTrashed() it is a real Builder method — Larastan cannot
            // resolve withTrashed() through a Builder-typed closure parameter, so the
            // explicit form is also the type-safe one.
            ->when($f->status === '' || $f->status === null, fn (Builder $q) => $q->withoutGlobalScope(\\Illuminate\\Database\\Eloquent\\SoftDeletingScope::class))`
        : ''
}
            ->when($f->dateFrom && $f->dateTo, fn (Builder $q) => $q->whereBetween('created_at', [
                \\Carbon\\CarbonImmutable::parse($f->dateFrom)->startOfDay(),
                \\Carbon\\CarbonImmutable::parse($f->dateTo)->endOfDay(),
            ]))
            ->orderBy(in_array($f->sortField, ['${sortable}'], true) ? $f->sortField : 'created_at', $f->sortOrder === 1 ? 'asc' : 'desc');
    }
${
    userOwned
        ? `
    /**
     * Owner scoping — the single choke point for this table.
     *
     * Every read and every write of a user-owned row must go through this scope
     * or through {@see self::assertOwned()}. A bare where('uuid', ...) on this model
     * is an IDOR waiting to happen: a uuid is unguessable, not secret, and
     * permission:* authorises the ACTION, never the ROW. The export is the case
     * that gets forgotten — an unfiltered export is the whole table.
     *
     * Takes the owner KEY, not a User, so an export or a job can scope without
     * carrying a session. Null means "no owner" and matches nothing.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOwnedBy(Builder $query, int|string|null $ownerKey): Builder
    {
        return $ownerKey === null
            ? $query->whereRaw('1 = 0')
            : $query->where($query->qualifyColumn('user_id'), $ownerKey);
    }

    /**
     * Load-one-and-check, in one auditable place.
     *
     * OWASP API1:2023 (BOLA). \`permission:*\` authorises the ACTION, never the
     * ROW, so the ownership check has to exist even though every route here is
     * permission-guarded. 404 for a row that does not exist, 403 for a row that
     * exists but belongs to someone else (project rule, owasp/STACK.md §11).
     *
     * Deliberately takes the owner's key and NOT a Request — transport does not
     * belong on a model. Pass \`$request->user()?->getKey()\`.
     */
    public static function assertOwned(?self $row, int|string|null $ownerKey): self
    {
        abort_if($row === null, 404);
        abort_unless($row->user_id === $ownerKey, 403);

        return $row;
    }
`
        : ''
}
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['uuid', '${spec.fields.map((f) => f.name).join("', '")}'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('${table}');
    }
}
`;

files[`${MOD}/Data/${Entity}Data.php`] = `${HEADER}
namespace Modules\\${Module}\\Data;

use Spatie\\LaravelData\\Attributes\\MapInputName;
use Spatie\\LaravelData\\Attributes\\MapOutputName;
use Spatie\\LaravelData\\Data;
use Spatie\\LaravelData\\Mappers\\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class ${Entity}Data extends Data
{
    public function __construct(
        public readonly string $uuid,
${spec.fields.map(dtoProp).join('\n')}
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}
}
`;

files[`${MOD}/Data/${Entity}FilterData.php`] = `${HEADER}
namespace Modules\\${Module}\\Data;

use Spatie\\LaravelData\\Attributes\\MapInputName;
use Spatie\\LaravelData\\Attributes\\Validation\\AfterOrEqual;
use Spatie\\LaravelData\\Attributes\\Validation\\BeforeOrEqual;
use Spatie\\LaravelData\\Attributes\\Validation\\Date;
use Spatie\\LaravelData\\Attributes\\Validation\\In;
use Spatie\\LaravelData\\Attributes\\Validation\\Max;
use Spatie\\LaravelData\\Attributes\\Validation\\Min;
use Spatie\\LaravelData\\Data;
use Spatie\\LaravelData\\Mappers\\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class ${Entity}FilterData extends Data
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $status = null,
        #[Date, BeforeOrEqual('date_to')]
        public readonly ?string $dateFrom = null,
        #[Date, AfterOrEqual('date_from')]
        public readonly ?string $dateTo = null,
        public readonly string $sortField = 'created_at',
        #[In([1, -1])]
        public readonly int $sortOrder = -1,
        #[Min(1)]
        public readonly int $page = 1,
        #[Min(1), Max(100)]
        public readonly int $perPage = 15,
    ) {}
}
`;

files[`${MOD}/Http/Controllers/${Entity}Controller.php`] = `${HEADER}
namespace Modules\\${Module}\\Http\\Controllers;

use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Inertia\\Inertia;
use Inertia\\Response as InertiaResponse;
use Modules\\${Module}\\Data\\${Entity}Data;
use Modules\\${Module}\\Data\\${Entity}FilterData;
use Modules\\${Module}\\Http\\Requests\\BulkDelete${Entity}Request;
use Modules\\${Module}\\Http\\Requests\\BulkRestore${Entity}Request;
use Modules\\${Module}\\Http\\Requests\\Store${Entity}Request;
use Modules\\${Module}\\Http\\Requests\\Update${Entity}Request;
use Modules\\${Module}\\Models\\${Entity};

/** Single fused controller: Inertia for browsers, JSON when $request->expectsJson(). */
final readonly class ${Entity}Controller
{
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $rows = ${Entity}::query()${ownedScope}->applyFilters(${Entity}FilterData::from($request))->paginate(15);

        if ($request->expectsJson()) {
            $rows->setCollection($rows->getCollection()->map(fn (${Entity} $m) => ${Entity}Data::from($m)));

            return response()->json($rows);
        }

        return Inertia::render('${Module}/Index', ['${pluralVar}' => ${Entity}Data::collect($rows)]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('${Module}/Create');
    }

    public function store(Store${Entity}Request $request): mixed
    {
        $row = new ${Entity}($request->validated());
${userOwned ? '$row->user_id = $request->user()->id;' : ''}
$row->save();

        if ($request->expectsJson()) {
            return response()->json(${Entity}Data::from($row), 201);
        }

        return redirect()->route('${route}.index');
    }

    public function show(Request $request, string $uuid): InertiaResponse|JsonResponse
    {
        $row = ${loadOwned};

        if ($request->expectsJson()) {
            return response()->json(${Entity}Data::from($row));
        }

        return Inertia::render('${Module}/Show', ['${singleVar}' => ${Entity}Data::from($row)]);
    }

    public function edit(Request $request, string $uuid): InertiaResponse
    {
        $row = ${loadOwned};

        return Inertia::render('${Module}/Edit', ['${singleVar}' => ${Entity}Data::from($row)]);
    }

    public function update(Update${Entity}Request $request, string $uuid): mixed
    {
        $row = ${loadOwned};
        $row->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json(${Entity}Data::from($row->refresh()));
        }

        return redirect()->route('${route}.index');
    }

    public function destroy(Request $request, string $uuid): mixed
    {
        ${loadOwned}->delete();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('${route}.index');
    }
${
    softDeletes
        ? `
    public function restore(Request $request, string $uuid): mixed
    {
        ${loadOwnedTrashed}->restore();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('${route}.index');
    }
`
        : ''
}${
    bulk
        ? `
    public function bulkDelete(BulkDelete${Entity}Request $request): mixed
    {
        $uuids = $request->validated()['uuids'];
${assertAllOwned(false)}${Entity}::query()${ownedScope}->whereIn('uuid', $uuids)->delete();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('${route}.index');
    }
${
    softDeletes
        ? `
    public function bulkRestore(BulkRestore${Entity}Request $request): mixed
    {
        $uuids = $request->validated()['uuids'];
${assertAllOwned(true)}${Entity}::withTrashed()${ownedScope}->whereIn('uuid', $uuids)->restore();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('${route}.index');
    }
`
        : ''
}`
        : ''
}
}
`;

files[`${MOD}/Http/Requests/Store${Entity}Request.php`] = `${HEADER}
namespace Modules\\${Module}\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;

final class Store${Entity}Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('CREATE_${PERM}') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
${rulesBlock(false)}
        ];
    }
}
`;

files[`${MOD}/Http/Requests/Update${Entity}Request.php`] = files[
    `${MOD}/Http/Requests/Store${Entity}Request.php`
]
    .replace(`final class Store${Entity}Request`, `final class Update${Entity}Request`)
    .replace(
        `return $this->user()?->can('CREATE_${PERM}') ?? false;`,
        `return $this->user()?->can('UPDATE_${PERM}') ?? false;`,
    )
    .replace(rulesBlock(false), rulesBlock(true));

if (bulk) {
    for (const [op, perm] of [
        ['Delete', `DELETE_${PERM}`],
        ['Restore', `RESTORE_${PERM}`],
    ]) {
        if (op === 'Restore' && !softDeletes) continue;
        files[`${MOD}/Http/Requests/Bulk${op}${Entity}Request.php`] = `${HEADER}
namespace Modules\\${Module}\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;

final class Bulk${op}${Entity}Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('${perm}') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuids' => ['required', 'array', 'min:1', 'max:500'],
            'uuids.*' => ['uuid', 'exists:${table},uuid'],
        ];
    }
}
`;
    }
}

/*
 * Exports.
 *
 * The module ships ONLY the transformation — a column map plus a lazy, owner-scoped
 * row stream. The writer, the streaming and the PDF renderer stay behind
 * Shared\Domain\Ports\ExportPort, exactly as that port's docblock demands:
 * "A module re-implementing the writer is a DRY FAIL."
 *
 * The previous version of this generator emitted its own `EntityCsvExport` with
 * `fopen('php://output')` + `fputcsv()`, which reimplemented `ExportPort::tabular()`,
 * duplicated the owner scope in a second place, and made xlsx/pdf unreachable.
 * Formats now come from the port: `ExportPort::tabular()` picks its driver from the
 * filename extension (csv, xlsx), and `ExportPort::pdf()` delegates to dompdf.
 */
const wantTabular = exports.includes('csv') || exports.includes('xlsx');
const wantPdf = exports.includes('pdf');
const wantExport = wantTabular || wantPdf;
const exportFormats = exports.filter((e) => ['csv', 'xlsx', 'pdf'].includes(e));
const formatList = exportFormats.map((f) => `'${f}'`).join(', ');

const exportUse = wantExport
    ? `use Modules\\${Module}\\Http\\Controllers\\${Entity}ExportController;\n`
    : '';
const exportRoute = wantExport
    ? `    Route::get('/export/{format}', [${Entity}ExportController::class, '__invoke'])
        ->whereIn('format', [${formatList}])
        ->middleware('permission:EXPORT_${PERM}')
        ->name('export');\n`
    : '';

/* Column labels for the export header, derived from the spec. */
const exportColumns = spec.fields.map(
    (f) => `            '${f.name}' => '${f.name.replace(/_/g, ' ')}',`,
);

if (wantExport) {
    // The module's only export file: WHAT a row looks like in a spreadsheet.
    // No response, no stream, no headers — that is the port's job.
    files[`${MOD}/Exports/${Entity}ExportTransformer.php`] = `${HEADER}
namespace Modules\\${Module}\\Exports;

use DateTimeInterface;
use Modules\\${Module}\\Data\\${Entity}FilterData;
use Modules\\${Module}\\Models\\${Entity};

/**
 * Row -> column transformation for this module's export.
 *
 * Deliberately NOT a response builder. The streaming, the driver selection and the
 * PDF rendering live behind {@see \\Shared\\Domain\\Ports\\ExportPort}; re-implementing
 * any of it here is a DRY fail and is how a second, unscoped data path appears.
 *
 * The query is scoped exactly like the list endpoint — same filters, same owner
 * scope. An export that skips either is the most common data leak in this stack:
 * same permission, same filters, and suddenly every user's rows.
 */
final readonly class ${Entity}ExportTransformer
{
    public function __construct(private ${Entity}FilterData $filters, private int|string|null $ownerKey = null) {}

    /**
     * Machine column name => human header label.
     *
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
${exportColumns.join('\n')}
        ];
    }

    /** @return list<string> */
    public function headers(): array
    {
        return array_values($this->columns());
    }

    /**
     * Lazy row stream. \`cursor()\` keeps memory flat on a large table and returns a
     * LazyCollection, which the PDF view can also iterate without materialising it.
     *
     * @return iterable<int, array<string, scalar|null>>
     */
    public function rows(): iterable
    {
        $columns = array_keys($this->columns());
        $query = ${Entity}::query()${userOwned ? '->ownedBy($this->ownerKey)' : ''}->applyFilters($this->filters);

        foreach ($query->cursor() as $row) {
            $cells = [];

            foreach ($columns as $column) {
                $cells[$column] = $this->cell($row->{$column});
            }

            yield $cells;
        }
    }

    /**
     * One export-safe cell value.
     *
     * PHP 8.5 pipe: a left-to-right normalisation chain with no temp variable.
     * A spreadsheet cell holds a scalar - never a Carbon, never a bool, never null.
     *
     * Note: "scalar" is NOT a valid native return type (it is a phpdoc-only pseudo
     * type, and a bare "scalar" here silently resolves to a class in this namespace),
     * so the union is spelled out.
     */
    private function cell(mixed $value): string|int|float
    {
        return $value
            |> (fn (mixed $v): mixed => $v instanceof DateTimeInterface ? $v->format(DATE_ATOM) : $v)
            |> (fn (mixed $v): mixed => is_bool($v) ? (int) $v : $v)
            |> (fn (mixed $v): int|float|string => $v ?? '');
    }
}
`;

    files[`${MOD}/Http/Controllers/${Entity}ExportController.php`] = `${HEADER}
namespace Modules\\${Module}\\Http\\Controllers;

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Response;
use Modules\\${Module}\\Data\\${Entity}FilterData;
use Modules\\${Module}\\Exports\\${Entity}ExportTransformer;
use Shared\\Domain\\Ports\\ExportPort;
use Symfony\\Component\\HttpFoundation\\StreamedResponse;

/**
 * Thin export controller. Resolves the shared port and hands it the module's
 * transformation — the mechanics are not duplicated here.
 *
 * Same filters and the same \`permission:EXPORT_*\` as \`index\`, so a filtered export
 * exports exactly what is on screen and nothing more.
 *
 * Return types are explicit so Scramble documents the export surface too.
 */
final readonly class ${Entity}ExportController
{
    public function __construct(private ExportPort $exporter) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $transformer = new ${Entity}ExportTransformer(
            ${Entity}FilterData::from($request),
            $request->user()?->getKey(),
        );

        $filename = '${table}.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.${table}', [
                'title' => '${Entity} export',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
`;
}

if (wantPdf) {
    // dompdf renders a Blade view; the module owns its own template.
    files[`resources/views/exports/${table}.blade.php`] =
        `{{-- Module export template for dompdf (barryvdh/laravel-dompdf).

     The only presentation a module owns in an export: a table. Everything else —
     streaming, driver selection, paper size, ownership scoping — is upstream.

     $columns: array<string, string>  machine column => header label
     $rows:    iterable<int, array<string, scalar|null>>  lazy, never materialised
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? '${Entity} export' }}</title>
    <style>
        /* dompdf supports a CSS subset. No grid, no flex, no custom properties. */
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #18181b; }
        h1 { font-size: 13pt; margin: 0 0 6mm; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left; padding: 4px 6px; background: #f4f4f5;
            border-bottom: 1px solid #d4d4d8; font-size: 8.5pt; text-transform: uppercase;
        }
        tbody td { padding: 3px 6px; border-bottom: 1px solid #f4f4f5; }
        tbody tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <h1>{{ $title ?? '${Entity} export' }}</h1>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
`;
}

files[`${MOD}/Routes/web.php`] = `${HEADER}
use Illuminate\\Support\\Facades\\Route;
use Modules\\${Module}\\Http\\Controllers\\${Entity}Controller;
${exportUse}

Route::middleware(['auth', 'verified'])->prefix('${route}')->name('${route}.')->group(function (): void {
    Route::get('/', [${Entity}Controller::class, 'index'])->middleware('permission:VIEW_${PERM}')->name('index');
    Route::get('/create', [${Entity}Controller::class, 'create'])->middleware('permission:CREATE_${PERM}')->name('create');
    Route::post('/', [${Entity}Controller::class, 'store'])->middleware('permission:CREATE_${PERM}')->name('store');
${
    bulk
        ? `    Route::post('/bulk-delete', [${Entity}Controller::class, 'bulkDelete'])->middleware('permission:DELETE_${PERM}')->name('bulk-delete');
${softDeletes ? `    Route::post('/bulk-restore', [${Entity}Controller::class, 'bulkRestore'])->middleware('permission:RESTORE_${PERM}')->name('bulk-restore');\n` : ''}`
        : ''
}${exportRoute}    Route::get('/{uuid}', [${Entity}Controller::class, 'show'])->middleware('permission:VIEW_${PERM}')->whereUuid('uuid')->name('show');
    Route::get('/{uuid}/edit', [${Entity}Controller::class, 'edit'])->middleware('permission:UPDATE_${PERM}')->whereUuid('uuid')->name('edit');
    Route::put('/{uuid}', [${Entity}Controller::class, 'update'])->middleware('permission:UPDATE_${PERM}')->whereUuid('uuid')->name('update');
    Route::delete('/{uuid}', [${Entity}Controller::class, 'destroy'])->middleware('permission:DELETE_${PERM}')->whereUuid('uuid')->name('destroy');
${softDeletes ? `    Route::patch('/{uuid}/restore', [${Entity}Controller::class, 'restore'])->middleware('permission:RESTORE_${PERM}')->whereUuid('uuid')->name('restore');\n` : ''}});
`;

if (api) {
    // Sanctum JSON API (mobile/external). Separate controller so Scramble
    // documents explicit JsonResponse shapes — never the fused web controller.
    files[`${MOD}/Http/Controllers/Api/${Entity}Controller.php`] = `${HEADER}
namespace Modules\\${Module}\\Http\\Controllers\\Api;

use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Response;
use Modules\\${Module}\\Data\\${Entity}Data;
use Modules\\${Module}\\Data\\${Entity}FilterData;
use Modules\\${Module}\\Http\\Requests\\BulkDelete${Entity}Request;
use Modules\\${Module}\\Http\\Requests\\BulkRestore${Entity}Request;
use Modules\\${Module}\\Http\\Requests\\Store${Entity}Request;
use Modules\\${Module}\\Http\\Requests\\Update${Entity}Request;
use Modules\\${Module}\\Models\\${Entity};

/** JSON-only API. Every method carries an explicit return type — Scramble reads these for OpenAPI. */
final readonly class ${Entity}Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = ${Entity}::query()${ownedScope}->applyFilters(${Entity}FilterData::from($request))->paginate(15);

        return response()->json(${Entity}Data::collect($rows));
    }

    public function store(Store${Entity}Request $request): JsonResponse
    {
        $row = new ${Entity}($request->validated());
${userOwned ? '$row->user_id = $request->user()->id;' : ''}
$row->save();

        return response()->json(${Entity}Data::from($row), 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        return response()->json(${Entity}Data::from(${loadOwned}));
    }

    public function update(Update${Entity}Request $request, string $uuid): JsonResponse
    {
        $row = ${loadOwned};
        $row->update($request->validated());

        return response()->json(${Entity}Data::from($row->refresh()));
    }

    public function destroy(Request $request, string $uuid): Response
    {
        ${loadOwned}->delete();

        return response()->noContent();
    }
${
    softDeletes
        ? `
    public function restore(Request $request, string $uuid): Response
    {
        ${loadOwnedTrashed}->restore();

        return response()->noContent();
    }
`
        : ''
}${
        bulk
            ? `
    public function bulkDelete(BulkDelete${Entity}Request $request): Response
    {
        $uuids = $request->validated()['uuids'];
${assertAllOwned(false)}${Entity}::query()${ownedScope}->whereIn('uuid', $uuids)->delete();

        return response()->noContent();
    }
${
    softDeletes
        ? `
    public function bulkRestore(BulkRestore${Entity}Request $request): Response
    {
        $uuids = $request->validated()['uuids'];
${assertAllOwned(true)}${Entity}::withTrashed()${ownedScope}->whereIn('uuid', $uuids)->restore();

        return response()->noContent();
    }
`
        : ''
}`
            : ''
    }
}
`;

    files[`${MOD}/Routes/api.php`] = `${HEADER}
use Illuminate\\Support\\Facades\\Route;
use Modules\\${Module}\\Http\\Controllers\\Api\\${Entity}Controller;

Route::middleware(['api', 'auth:sanctum', 'throttle:60,1'])->prefix('api/${route}')->name('api.${route}.')->group(function (): void {
    Route::get('/', [${Entity}Controller::class, 'index'])->middleware('permission:VIEW_${PERM}')->name('index');
    Route::post('/', [${Entity}Controller::class, 'store'])->middleware('permission:CREATE_${PERM}')->name('store');
${
    bulk
        ? `    Route::post('/bulk-delete', [${Entity}Controller::class, 'bulkDelete'])->middleware('permission:DELETE_${PERM}')->name('bulk-delete');
${softDeletes ? `    Route::post('/bulk-restore', [${Entity}Controller::class, 'bulkRestore'])->middleware('permission:RESTORE_${PERM}')->name('bulk-restore');\n` : ''}`
        : ''
}    Route::get('/{uuid}', [${Entity}Controller::class, 'show'])->middleware('permission:VIEW_${PERM}')->whereUuid('uuid')->name('show');
    Route::put('/{uuid}', [${Entity}Controller::class, 'update'])->middleware('permission:UPDATE_${PERM}')->whereUuid('uuid')->name('update');
    Route::delete('/{uuid}', [${Entity}Controller::class, 'destroy'])->middleware('permission:DELETE_${PERM}')->whereUuid('uuid')->name('destroy');
${softDeletes ? `    Route::patch('/{uuid}/restore', [${Entity}Controller::class, 'restore'])->middleware('permission:RESTORE_${PERM}')->whereUuid('uuid')->name('restore');\n` : ''}});
`;
}

files[`${MOD}/Providers/${Module}ServiceProvider.php`] = `${HEADER}
namespace Modules\\${Module}\\Providers;

use Illuminate\\Support\\ServiceProvider;

final class ${Module}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
${api ? "        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');\n" : ''}    }
}
`;

const now = new Date();
const stamp = `${now.getUTCFullYear()}_${String(now.getUTCMonth() + 1).padStart(2, '0')}_${String(now.getUTCDate()).padStart(2, '0')}_${String(now.getUTCHours()).padStart(2, '0')}${String(now.getUTCMinutes()).padStart(2, '0')}${String(now.getUTCSeconds()).padStart(2, '0')}`;

files[`database/migrations/${stamp}_create_${table}_table.php`] = `${HEADER}
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('${table}', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
${userOwned ? "            $table->foreignId('user_id')->constrained()->cascadeOnDelete();\n" : ''}${spec.fields.map((f) => `            ${migCol(f)}`).join('\n')}
            $table->timestamps();
${softDeletes ? '            $table->softDeletes();\n' : ''}${softDeletes ? "            $table->index(['deleted_at', 'created_at']);\n" : ''}        });
    }

    public function down(): void
    {
        Schema::dropIfExists('${table}');
    }
};
`;

files[`database/factories/${Entity}Factory.php`] = `${HEADER}
namespace Database\\Factories;

use Illuminate\\Database\\Eloquent\\Factories\\Factory;
use Illuminate\\Support\\Str;
use Modules\\${Module}\\Models\\${Entity};

/** @extends Factory<${Entity}> */
final class ${Entity}Factory extends Factory
{
    protected $model = ${Entity}::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
${spec.fields.map((f) => `            '${f.name}' => ${fakeFor(f)},`).join('\n')}
        ];
    }
}
`;

function fakeFor(f) {
    switch (f.type) {
        // Respect max:length so factory output always passes Store validation.
        case 'string':
            return f.length
                ? `Str::substr(fake()->words(3, true), 0, ${f.length})`
                : `fake()->words(3, true)`;
        case 'text':
            return `fake()->words(3, true)`;
        case 'integer':
        case 'foreign':
            return 'fake()->numberBetween(1, 100)';
        case 'boolean':
            return 'fake()->boolean()';
        case 'date':
            return 'fake()->date()';
        case 'datetime':
            return 'fake()->dateTime()->format(\\DateTimeInterface::ATOM)';
        case 'decimal':
            return 'fake()->randomFloat(2, 1, 999)';
        case 'uuid':
            return '(string) \\Illuminate\\Support\\Str::uuid7()';
        case 'json':
            return `['a' => 1]`;
        default:
            return `'x'`;
    }
}

files[`${MOD}/Tests/Feature/${Entity}CrudTest.php`] = `${HEADER}
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Modules\\${Module}\\Models\\${Entity};
use Spatie\\Permission\\Models\\Permission;
use Spatie\\Permission\\Models\\Role;
use Spatie\\Permission\\PermissionRegistrar;
use Tests\\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

use function Pest\\Laravel\\actingAs;
use function Pest\\Laravel\\assertDatabaseHas;
use function Pest\\Laravel\\get;
use function Pest\\Laravel\\post;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([${permsPhp}] as $name) {
        Permission::findOrCreate($name);
    }

    $role = Role::findOrCreate('tester');
    $role->givePermissionTo(Permission::all());

    $this->user = User::factory()->create();
    $this->user->assignRole($role);
});

/* ------------------------------------------------------ 401 unauthenticated */

it('redirects guests to login', function (): void {
    get('/${route}')->assertRedirect('/login');
});

it('refuses a JSON write for a guest with 401', function (): void {
    // A guest hitting the web surface is redirected to login; a JSON request gets a
    // real 401. Both are unauthenticated refusals — neither reaches the controller.
    $this->postJson('/${route}', [])->assertUnauthorized();
});

it('lists rows for an authorised user', function (): void {
    actingAs($this->user)->get('/${route}')->assertOk();
});

/* ------------------------------------------- 403 function-level (no permission) */

it('refuses a user without the permission with 403', function (): void {
    // Function-level authz (OWASP API5:2023) - distinct from ownership below.
    $row = ${Entity}::factory()->create(${userOwned ? "['user_id' => \$this->user->id]" : '[]'});

    actingAs(User::factory()->create())
        ->getJson("/${route}/{\$row->uuid}")
        ->assertForbidden();
});

/* ------------------------------------------------------ 422 invalid input */

it('rejects invalid input with 422', function (): void {
    actingAs($this->user)
        ->postJson('/${route}', [])
        ->assertStatus(422);
});
${
    userOwned
        ? `
/* --------------------------------------- 403 object-level (someone else's row) */

it('refuses a row owned by another user with 403 and hides it from the list and the export', function (): void {
    // Object-level authz (OWASP API1:2023). Every one of these was a full read or
    // write of someone else's row before the owner scope existed - a uuid is
    // unguessable, not secret, and permission:* never authorised the row.
    $other = User::factory()->create();
    $other->assignRole('tester');

    $row = ${Entity}::factory()->create(['user_id' => $other->id]);
    $payload = ${Entity}::factory()->make()->toArray();

    actingAs($this->user)->getJson("/${route}/{\$row->uuid}")->assertForbidden();
    actingAs($this->user)->putJson("/${route}/{\$row->uuid}", \$payload)->assertForbidden();
    actingAs($this->user)->deleteJson("/${route}/{\$row->uuid}")->assertForbidden();

    $listed = actingAs($this->user)->getJson('/${route}')->assertOk()->json('data');
    expect(collect($listed)->pluck('uuid'))->not->toContain($row->uuid);
${
    wantExport
        ? `
    // Same owner scope through the export path. Only the tabular formats are a
    // StreamedResponse whose bytes we can search; PDF is a rendered Response, so it
    // is asserted on status only — the scope itself lives in rows(), which the CSV
    // and XLSX assertions below already prove.
${exportFormats
    .filter((f) => f !== 'pdf')
    .map(
        (f) =>
            `    expect(actingAs(\$this->user)->get('/${route}/export/${f}')->assertOk()->streamedContent())->not->toContain(\$row->uuid);`,
    )
    .join(
        '\n',
    )}${wantPdf ? `\n    actingAs(\$this->user)->get('/${route}/export/pdf')->assertOk();` : ''}
`
        : ''
}
    // The refused requests must not have mutated anything.
    expect(${Entity}::withTrashed()->where('uuid', $row->uuid)->exists())->toBeTrue();
});
`
        : ''
}
/* ------------------------------------------------------------ happy path */

it('creates, updates, deletes${softDeletes ? ' and restores' : ''} a row', function (): void {
    $payload = ${Entity}::factory()->make()->toArray();

    $uuid = actingAs($this->user)
        ->postJson('/${route}', $payload)
        ->assertCreated()
        ->json('uuid');

    expect($uuid)->toBeString();
    assertDatabaseHas('${table}', ['uuid' => $uuid]);

    actingAs($this->user)
        ->putJson("/${route}/{$uuid}", $payload)
        ->assertOk();

    actingAs($this->user)->deleteJson("/${route}/{$uuid}")->assertNoContent();
${
    softDeletes
        ? `
    expect(${Entity}::withTrashed()->where('uuid', $uuid)->exists())->toBeTrue();

    actingAs($this->user)->patchJson("/${route}/{$uuid}/restore")->assertNoContent();
`
        : ''
}
});${
    wantExport
        ? `
/* --------------------------------------------------------------- exports */

it('exports the filtered list in every requested format', function (): void {
    ${Entity}::factory()->count(3)->create(${userOwned ? "['user_id' => \$this->user->id]" : '[]'});

    $expectations = [
${exportFormats
    .map(
        (f) =>
            `        '${f}' => '${
                f === 'pdf'
                    ? 'application/pdf'
                    : f === 'xlsx'
                      ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                      : 'text/csv'
            }',`,
    )
    .join('\n')}
    ];

    foreach ($expectations as $format => $contentType) {
        $response = actingAs($this->user)->get("/${route}/export/{$format}")->assertOk();

        expect($response->headers->get('content-type'))->toStartWith($contentType);
        expect($response->headers->get('content-disposition'))->toContain('${table}.'.$format);
    }
});

it('refuses an unknown export format with 404', function (): void {
    // The route is constrained to the declared formats, so a typo from the frontend
    // is a 404 instead of an unhandled format reaching the writer.
    actingAs($this->user)->get('/${route}/export/csvx')->assertNotFound();
});`
        : ''
}${
    bulk
        ? `
/* ------------------------------------------------------------------ bulk */

it('bulk deletes${softDeletes ? ' and bulk restores' : ''} selected rows', function (): void {
    $uuids = ${Entity}::factory()->count(3)->create(${userOwned ? "['user_id' => \$this->user->id]" : '[]'})->pluck('uuid')->all();

    actingAs($this->user)
        ->postJson('/${route}/bulk-delete', ['uuids' => $uuids])
        ->assertNoContent();
${
    softDeletes
        ? `
    actingAs($this->user)
        ->postJson('/${route}/bulk-restore', ['uuids' => $uuids])
        ->assertNoContent();
`
        : ''
}
});`
        : ''
}
`;

/* ------------------------------------------------------- guarded patches */

const patches = [];

/*
 * A patch that reports "patched" when it changed nothing is worse than no patch:
 * it hides a broken regex behind a green log line. `applied` turns a silently
 * failed replacement into a hard error instead.
 */
const applied = (label, before, after) => {
    if (before === after) {
        fail(
            `${label}: no match — the target file's shape changed. The patch would have been a silent no-op.`,
        );
    }
    return after;
};

if (userOwned) {
    patches.push({
        file: 'app/Models/User.php',
        describe: `inverse ${hasManyName}() hasMany on User`,
        apply: (src) => {
            if (src.includes(`function ${hasManyName}()`)) return { src, changed: false };

            let out = src;

            if (!out.includes(`use Modules\\${Module}\\Models\\${Entity};`)) {
                out = applied(
                    'User.php import of the module model',
                    out,
                    out.replace(
                        'use Database\\Factories\\UserFactory;',
                        `use Database\\Factories\\UserFactory;\nuse Modules\\${Module}\\Models\\${Entity};`,
                    ),
                );
            }

            if (!out.includes('use Illuminate\\Database\\Eloquent\\Relations\\HasMany;')) {
                out = applied(
                    'User.php import of HasMany',
                    out,
                    out.replace(
                        'use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;',
                        'use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\nuse Illuminate\\Database\\Eloquent\\Relations\\HasMany;',
                    ),
                );
            }

            const method = `\n    /** @return HasMany<${Entity}, $this> */\n    public function ${hasManyName}(): HasMany\n    {\n        return $this->hasMany(${Entity}::class);\n    }\n}`;

            // The file ends with "}\n", so `\n\}$` never matched: the method was
            // silently never added, the patch still reported success, and Pint then
            // deleted the imports it had added. `\s*$` absorbs the trailing newline.
            out = applied(
                'User.php inverse hasMany method',
                out,
                out.replace(/\n\}\s*$/, `${method}\n`),
            );

            return { src: out, changed: true };
        },
    });
}

patches.push({
    file: 'bootstrap/providers.php',
    describe: `${Module}ServiceProvider registration`,
    apply: (src) => {
        // Accept either form already present, so a second run is a no-op instead
        // of appending a duplicate entry.
        const short = `    ${Module}ServiceProvider::class,`;
        const qualified = `    Modules\\${Module}\\Providers\\${Module}ServiceProvider::class,`;
        if (src.includes(short) || src.includes(qualified)) return { src, changed: false };

        let out = src;

        // providers.php is in pint.json's exclude list, so Pint will not add this
        // import. Without it the short name below resolves against the global
        // namespace and the provider is never found.
        const use = `use Modules\\${Module}\\Providers\\${Module}ServiceProvider;`;
        if (!out.includes(use)) {
            out = applied(
                'providers.php import of the module provider',
                out,
                out.replace(/^(use .+;\n)/m, `$1${use}\n`),
            );
        }

        out = applied(
            'providers.php provider registration',
            out,
            out.replace(/\];\s*$/, `${short}\n];\n`),
        );

        return { src: out, changed: true };
    },
});

/* ------------------------------------------------------------------ write */

const writeFile = (rel, content) => {
    const abs = join(ROOT, rel);
    mkdirSync(dirname(abs), { recursive: true });
    writeFileSync(abs, content);
};

if (DRY) {
    console.log(
        `module-make: DRY-RUN — ${Object.keys(files).length} files + ${patches.length} patches:\n`,
    );
    for (const rel of Object.keys(files)) console.log(`  + ${rel}`);
    for (const p of patches) console.log(`  ~ ${p.file} (${p.describe})`);
    console.log('\nSeeder snippet (add to your RolePermissionSeeder):');
    console.log(seederSnippet());
    process.exit(0);
}

if (existsSync(join(ROOT, MOD)) && !FORCE) {
    if (SKIP_EXISTING) {
        console.log(
            `module-make: SKIP — ${MOD}/ already exists (remove it or batch with --force to regenerate).`,
        );
        process.exit(0);
    }
    fail(`${MOD}/ already exists. Delete it or re-run with --force.`);
}

for (const [rel, content] of Object.entries(files)) writeFile(rel, content);

for (const p of patches) {
    const abs = join(ROOT, p.file);
    const before = readFileSync(abs, 'utf8');
    const { src, changed } = p.apply(before);
    if (changed) writeFileSync(abs, src);
    console.log(`module-make: ${changed ? 'patched' : 'already ok'} — ${p.file} (${p.describe})`);
}

try {
    execSync(
        `php vendor/bin/pint ${MOD} database/migrations database/factories app/Models/User.php bootstrap/providers.php --format agent`,
        { cwd: ROOT, stdio: 'pipe' },
    );
    console.log('module-make: pint clean.');
} catch {
    warn('pint failed — run `vendor/bin/pint` manually.');
}

console.log(`\nmodule-make: ${Object.keys(files).length} files written for ${Module} (${Entity}).`);
console.log('\nSeeder snippet (add to your RolePermissionSeeder):');
console.log(seederSnippet());
console.log(`
Next (see .opencode/commands/backend-new-crud.md PHASE 4-5):
  composer dump-autoload
  php artisan migrate
  php artisan test --compact --filter=${Module}
  # frontend still manual: /frontend-new (Index/Show/Create/Edit + Colada queries)
  # complex module? STOP — promote to /backend-new (PROGRESSIVE), this output is the wrong shape.
`);

function seederSnippet() {
    return `  // ${Module} permissions
  foreach ([${permsPhp}] as $name) {
      Permission::findOrCreate($name);
  }
  app(\\Spatie\\Permission\\PermissionRegistrar::class)->forgetCachedPermissions();`;
}
