---
name: backend-php
description: PHP 8.6 and Laravel 13 rules — strict types, the genuine 8.6 feature set, what is NOT 8.6, Laravel 11/12 deprecations banned in 13, Eloquent and N+1 rules, Spatie patterns, canonical Action/FormRequest/Pest contracts, routes, exports, resilience and the composer package map. For directory layout and complexity modes see backend-architecture.
---

# BACKEND-PHP — PHP 8.6 + Laravel 13

> Layout, layers and complexity modes → `backend-php/SKILL-ARCHITECTURE.md`.
> Security → `owasp/SKILL.md` (always on).

## 0. Strict protocol

`declare(strict_types=1);` on every file. Native types everywhere — no `array` where a shape is known,
no untyped params, no `mixed` without a narrowing guard at the boundary.

## 1. PHP 8.6 features that are real

Verified against PHP 8.6.0 (released 2026-11-19). All of these exist:

| Feature                                                                                                                    | Use for                                                                                                                   |
| -------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| Partial function application (`?` / `...`)                                                                                 | Pre-fill arguments and get a Closure; replaces one-off closures (`version_compare(?, '8.6', '>=')`)                       |
| New `\Time\Duration` class                                                                                                 | Elapsed time, timeouts and benchmark math independent of date/timezone; comparable with `min()`/`clamp()`                 |
| `Io\Poll` (`Context`, `Event`, `StreamPollHandle`)                                                                         | Scalable I/O multiplexing for event loops/workers; not needed in a normal HTTP request                                    |
| `StreamException` + `StreamErrorMode`                                                                                      | Structured stream errors instead of `@` + `error_get_last()`; use for file/network streams                                |
| `clamp()`                                                                                                                  | Bound a comparable value between min/max in one call                                                                      |
| `SortDirection` enum (`Ascending` / `Descending`)                                                                          | Type-safe sort direction in public APIs instead of int/string flags                                                       |
| Parameter DocComments + `ReflectionParameter::getDocComment()`                                                             | Document a parameter next to its declaration for generators/tooling                                                       |
| `ReflectionProperty::isReadable()` / `isWritable()`                                                                        | Visibility-, hook- and initialization-aware reflection                                                                    |
| Readonly property defaults                                                                                                 | A readonly property can declare its default; the default counts as initialization and the constructor cannot overwrite it |
| `#[\Override]` on class constants/enum cases; enum `__debugInfo()`; `Uri\...UriBuilder`; Sodium PQ; TLS session resumption | Real 8.6 additions; use only where the feature fits                                                                       |

### 8.5 features still valid in 8.6

These are **8.5**, not 8.6 — do not relabel them:

| Feature                                           | Use for                                                                                 |
| ------------------------------------------------- | --------------------------------------------------------------------------------------- |
| Pipe operator `\|>`                               | Chained transformations without a temp variable. RHS closures **must** be parenthesised |
| Clone with — `clone($obj, [...])`                 | Re-initialise a `readonly` object on clone. **Function form only** (see §11.1)          |
| `Uri\Rfc3986\Uri`, `Uri\WhatWg\Url`               | URI parsing (8.6 adds the builder classes)                                              |
| `array_first()` / `array_last()`                  | First/last element without `reset()`/`end()` side effects                               |
| `#[\NoDiscard]`                                   | Functions whose return value must not be dropped                                        |
| `FILTER_THROW_ON_FAILURE`                         | Throw instead of returning `false` on bad filter input                                  |
| `#[Deprecated]` on traits                         | Deprecate a trait without breaking implementors                                         |
| `#[\Override]` on properties                      | The attribute targets properties (8.6 adds class constants/enum cases)                  |
| `get_error_handler()` / `get_exception_handler()` | Introspect the active handlers                                                          |
| Persistent cURL share handles                     | `curl_share_init_persistent()` — reuse DNS/TCP/TLS across requests                      |
| Error backtraces                                  | Fatal errors now include a backtrace                                                    |

## 2. Features that are NOT 8.6 — do not mislabel them

- **Pipe operator `|>` and `clone($obj, [...])` are 8.5**, not 8.6.
- **Property hooks** are **PHP 8.4**.
- **Asymmetric visibility (`private(set)`)** is **8.4** for properties; 8.5 extends it to _static_
  properties.
- **Partial function application, `\Time\Duration`, `Io\Poll`, `clamp()`, `SortDirection`, readonly
  property defaults and parameter DocComments are 8.6** — they do not exist in 8.5.
- Enums, readonly classes, first-class callable syntax — 8.1/8.2.
- `never`, `array_is_list()` — 8.1.

Writing "8.6 pipe operator", "8.6 property hooks" or "8.5 partial function application" is a red flag
in review. The 8.5 pipe and `clone($obj, [...])` remain the two you will actually reach for.

## 3. Never emit — deprecated or removed

| Do not                                                                                                                              | Use instead                                                           |
| ----------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- |
| `HasVersion7Uuids`                                                                                                                  | `HasUuids` (UUIDv7 since Laravel 12)                                  |
| `VerifyCsrfToken`                                                                                                                   | `PreventRequestForgery` (Laravel 13 renamed it)                       |
| `Inertia::lazy()`                                                                                                                   | `Inertia::optional()` / `Inertia::defer()`                            |
| `router.cancel()`                                                                                                                   | `router.cancelAll()`                                                  |
| `JsonResource` for an endpoint the frontend reads                                                                                   | Spatie `Data` (see §5)                                                |
| `whereDate('col', $date)` with a bad column                                                                                         | validate the column exists first                                      |
| `dispatchSync()` in HTTP handlers                                                                                                   | `dispatch()` — the queue is configured for `database`                 |
| `Storage::disk()->url()` for private files                                                                                          | signed URLs, always                                                   |
| `protected $casts` with a raw string array                                                                                          | typed casts or Spatie casts                                           |
| `Optional`/`Required` annotations                                                                                                   | native types                                                          |
| `dispatch()->afterResponse()` for anything durable                                                                                  | a queued job                                                          |
| `dd` / `dump` / `ray`                                                                                                               | never — an arch test fails the build on these                         |
| `return` inside `finally`                                                                                                           | restructure the `try`/`catch`; a `finally` return discards exceptions |
| `mb_ereg*` / `mb_split`                                                                                                             | PCRE with the `u` modifier (Oniguruma is unmaintained)                |
| `is_double`, `is_long`, `is_integer`, `doubleval`                                                                                   | `is_float`, `is_int`, `floatval`                                      |
| `spl_object_hash` / `spl_classes`                                                                                                   | `spl_object_id` / `ReflectionExtension::getClassNames()`              |
| Passing an object to `array_walk`, `http_build_query`, etc.                                                                         | `get_object_vars()` first                                             |
| `strcoll`, `SORT_LOCALE_STRING`                                                                                                     | `Collator` from intl                                                  |
| `metaphone`, `mysqli_get_charset`, `mysqli_stmt_init`, `SplFileObject` CSV methods, `ArrayIterator` sort/flag/serialization methods | maintained equivalents                                                |
| Custom session handler without `create_sid()` + `validateId()`                                                                      | implement both, or use the framework handler                          |

PHP 8.6 also turns many silent/truncating failures into `ValueError` and makes `fclose()`,
`file_put_contents()` and `copy()` return `false` on a failed flush/close. Do not rely on the old
silent behaviour. `trim()`, `ltrim()` and `rtrim()` now also strip form feed (`\f`).

Renamed-but-kept (`#[\Override]` on methods still works, `LogOptions` moved namespace in
activitylog v5) are in §5, not here — a rename is a compile error you will see, a _deprecation_ is not.

## 4. Laravel 13 rules

**Service providers.** Register app providers in `bootstrap/providers.php`. Module providers are
hand-registered — Laravel 11+ only auto-discovers `app/Providers/`.

**Models.** `HasUuids` for the public UUID identifier (UUIDv7 since Laravel 12). This repo keeps an
internal numeric `id`; `uniqueIds()` returns `['uuid']`, and `uuid` is the only identifier that
crosses HTTP. Declare `$fillable` explicitly — never `$guarded = []` on a model with a
mass-assignment path.

Strict mode is environment-split, and this is deliberate:

```php
// AppServiceProvider::boot()
Model::shouldBeStrict(! $this->app->isProduction());   // missing attributes / discarded input
Model::preventLazyLoading();                           // keep prevention on in production too

if ($this->app->isProduction()) {
    Model::handleLazyLoadingViolationUsing(function ($model, $relation): void {
        logger()->warning('Lazy loading ['.$relation.'] on ['.get_class($model).']');
    });
}
```

Local throws so an N+1 is a stack trace you cannot ignore; production logs so a lazy load on a rarely
hit branch degrades one request instead of a 500. **`shouldBeStrict(false)` alone does not log:** it
sets `preventsLazyLoading = false`, and Laravel only invokes the violation callback while prevention
is enabled — the callback would be dead code. Keep `preventLazyLoading()` on unconditionally and let
the production callback replace the throw. This is what makes the N+1 rules enforceable rather than
aspirational.

**N+1 prevention — mandatory.**

```php
// ✗ N+1
foreach (User::all() as $u) { $u->posts->count(); }

// ✓ explicit columns
User::with('posts:id,user_id,title')->withCount('posts')->get();

// ✓ relation + filter in one query
Post::withWhereHas('author', fn ($q) => $q->where('active', true))->get();
```

Never a bare `with('posts')` on a list endpoint — it loads every column of every related row.

**Queued credential links.** A job that mails a signed link or a password reset re-checks its
terminal states after `fresh()` — `trashed()`, the lifecycle status and the single-use marker.
`deleteWhenMissingModels` does not cover soft deletes, and `fresh()` bypasses the soft-delete scope,
so a deleted account still passes `isActive()`/`isInvited()`. Return without sending.

**Transactions** wrap multi-write operations only, and never around an HTTP call or a dispatch.

## 5. Spatie patterns

**Data** (`spatie/laravel-data` v4) is the default response shape. For Inertia props and the app's own
JSON alike. A classic `JsonResource` subclass in a new module is a FAIL.

**Never redeclare `Data::from()` with a narrower parameter.** Spatie declares
`from(mixed ...$payloads): static`; a `from(Invoice $invoice): self` override is a PHP fatal
(contravariance). Use the inherited `from($model)` or name the typed factory `fromModel()`.

**The one exception — Laravel 13 JSON:API.** `Illuminate\Http\Resources\JsonApi\JsonApiResource`
(`php artisan make:resource X --json-api`) is now first-party and is the _correct_ tool when you are
building a **public, spec-compliant API** for a third party: it handles relationship inclusion, sparse
fieldsets, `links` and JSON:API response headers for you.

| Surface                                                               | Use               |
| --------------------------------------------------------------------- | ----------------- |
| Inertia page props                                                    | Spatie `Data`     |
| The app's own JSON endpoints                                          | Spatie `Data`     |
| A public API a third party consumes, and you want JSON:API compliance | `JsonApiResource` |

Do not mix the two on one endpoint. The reason `Data` is the default is that
`typescript:transform` reads it to generate the frontend types — a `JsonApiResource` has no
equivalent, so using it for an internal endpoint silently breaks the generated types.

When a module does expose a public API, the JSON:API resource gets **its own controller**
(`Http/Controllers/Api/`), not a branch inside the web controller. That is what `module-make` generates
when the spec sets `api: true`, and it is also what makes the Scramble surface unambiguous.

```php
final class InvoiceData extends Data
{
    public function __construct(public string $number, public int $totalMinor) {}
    public static function fromModel(Invoice $i): self { /* ... */ }
}
```

**LogsActivity** — note the v5 namespaces, the v4 forms fatal at runtime:

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;   // NOT ...\Traits\LogsActivity
use Spatie\Activitylog\Support\LogOptions;             // NOT Spatie\Activitylog\LogOptions

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()->logOnly(['id', 'total'])->logOnlyDirty()->dontLogEmptyChanges();
}
```

**Permission** — check with `permission:*` middleware on routes and `@can` in Blade. The frontend
receives `permissions`, never `roles`, and hides UI accordingly.

## 6. Routes

- **Web** is primary: Inertia + session. **API** is secondary: Sanctum only, mobile/external.
- Every mutating verb carries a permission check.
- UUID params: `->whereUuid('uuid')`.
- Web requests: `FormRequest` validation for **mutating** endpoints. A read-only list/export endpoint
  validates through its shared `{Entity}FilterData` — the export must consume the same contract, so a
  pass-through `FormRequest` around a filter DTO is ceremony, not a boundary. Never `$request->validate()`
  inline in a controller that also does something else.

## 7. Exports (when in scope)

`spatie/simple-excel` for Excel (streaming, replaces Maatwebsite), `barryvdh/laravel-dompdf` for PDF.
Both are behind `Shared\Domain\Ports\ExportPort`; a module ships only its `{Entity}ExportTransformer`
and a thin controller. In a PROGRESSIVE module the transformer belongs in `Application/Exports/`
(the controller may import `Application/`, never `Infrastructure/`); in a FLAT module in `Exports/`
beside the controller. **Never in a controller.**

Export must apply the **same** filters and the **same** permission check as the list endpoint — an
export that ignores authorisation is a data leak, and it is the most common one in this stack. The
generator encodes this as a contract: the export reads the same `{Entity}FilterData` and the same
`permission:EXPORT_*` family as `index`.

## 8. OpenAPI — `dedoc/scramble`

Auto-generates OpenAPI 3.1 from controllers and Spatie Data. Export with
`php artisan scramble:export` after changing any controller or DTO. Never hand-edit the spec.

## 9. Resilience

Rate-limit, timeout and retry anything that leaves the process (AI, payments, webhooks). A call with no
timeout is an outage waiting for a slow Tuesday.

```php
Http::timeout(10)->retry(3, throw: false)->post($url, $payload);
```

`retry(..., throw: false)` is deliberate — surface the failure, do not hide it behind an exception the
caller will not catch.

## 10. Package map

| Package                                 | Version    | Belongs in                                                       |
| --------------------------------------- | ---------- | ---------------------------------------------------------------- |
| `laravel/framework`                     | 13.44      | —                                                                |
| `laravel/fortify`                       | ^1.40      | auth — the domain is Fortify's, not ours                         |
| `laravel/passkeys`                      | transitive | auth                                                             |
| `laravel/sanctum`                       | ^4.0       | API tokens                                                       |
| `laravel/telescope`                     | ^5.25      | `app/Providers/TelescopeServiceProvider.php`                     |
| `laravel/ai`                            | **^1.0**   | `Infrastructure/ExternalServices/` behind an `AIClientInterface` |
| `spatie/laravel-data`                   | ^4.23      | response shapes                                                  |
| `spatie/laravel-permission`             | ^8.3       | auth                                                             |
| `spatie/laravel-activitylog`            | ^5.1       | audit                                                            |
| `spatie/laravel-medialibrary`           | ^11.23     | files                                                            |
| `spatie/simple-excel`                   | ^3.10      | exports                                                          |
| `barryvdh/laravel-dompdf`               | ^3.1       | exports                                                          |
| `spatie/laravel-typescript-transformer` | ^3.3       | generates frontend types                                         |
| `dedoc/scramble`                        | ^0.13      | OpenAPI                                                          |
| `pragmarx/google2fa-laravel`            | ^3.0       | auth                                                             |
| `spatie/laravel-one-time-passwords`     | ^1.1       | auth                                                             |
| `inertiajs/inertia-laravel`             | 3.4.0      | frontend bridge                                                  |

**Never install `prism-php/prism` alongside `laravel/ai`** — `laravel/ai` uses Prism underneath. Two
bridges means two code paths for the same call.

---

## 11. Canonical snippets

These are **contracts, not examples** — the shape is the specification, so keep them exact. The CRUD
_implementations_ that used to sit here were removed on purpose; they duplicate the framework docs and
aged into fiction. What follows is the minimum that a reviewer needs to check a module against.

### 11.0 PHP 8.6 — partial application, Duration, clamp

```php
$atLeast86 = array_filter($versions, version_compare(?, '8.6', '>='));

$wait = clamp(
    $elapsed,
    \Time\Duration::fromSeconds(1),
    \Time\Duration::fromSeconds(30),
);
```

PFA placeholders are positional (`?`) and variadic (`...`); the result is a `Closure`. Do not use it
to hide a dependency — the resulting call must still be readable. `clamp()` works with any comparable
value, including `\Time\Duration`.

### 11.1 PHP 8.5 (still valid) — clone with

```php
$copy = clone($order, ['total' => 0]);   // ONLY valid syntax is the function form
```

`clone($obj, [...])` respects `__clone()`, fires property hooks, honours
type/visibility, lifts `readonly` write-once during the clone, and is usable as
callable `clone(...)`. The braced `clone $obj { ... }` form was proposed but
**never implemented** — emitting it is a syntax error.

### 11.2 Model template

```php
#[Fillable(['name', 'email'])]
class {Entity} extends Model
{
    use HasUuids, SoftDeletes, LogsActivity;   // HasUuids = UUIDv7

    protected function casts(): array { /* ... */ }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['id', 'total'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
```

Plus the environment-split `Model::shouldBeStrict(! isProduction())` from §4 — no lazy loading, no
missing attributes, no silently discarded attributes, thrown locally and logged in production.

### 11.3 DTO template — this is what generates the frontend types

```php
#[MapInputName(SnakeCaseMapper::class)]      // preserves the snake_case contract
#[MapOutputName(SnakeCaseMapper::class)]
final class {Entity}Data extends Data
{
    public function __construct(
        #[Required, Email, Max(255)] public readonly string $email,
        #[Required, AfterOrEqual('date_from')] public readonly ?string $dateTo = null,
    ) {}

    public static function fromModel({Entity} $m): self { /* ... */ }
    public static function collect(iterable $rows): self { /* ... */ }
}
```

`typescript:transform` reads these classes. **Change a `Data` class and you must rerun it** — that is
the whole point of not hand-writing the TypeScript.

### 11.4 Route skeleton

```php
Route::get('/{uuid}', ShowController::class)->name('show')->whereUuid('uuid');
```

`whereUuid` is not decoration: it 404s a malformed id before it reaches the model binding, which is
one of the OWASP §1 controls. Never expose a raw `id`.

### 11.5 Export skeleton

```php
SimpleExcelWriter::create($path)->writer(...)  // spatie/simple-excel, streaming
Pdf::loadView('exports.pdf.{entity}', [...])  // barryvdh/laravel-dompdf
```

The export must apply the **same** filters and the **same** permission check as the list endpoint. An
export that ignores authorisation is a data leak, and it is the most common one in this stack.

### 11.6 Resilience wrapper

```php
final class {Provider}Adapter implements {Provider}Interface
{
    public function __construct(
        private readonly CircuitBreakerInterface $breaker,
        private readonly RateLimiterInterface $limiter,
    ) {}

    public function send(Request $r): Response
    {
        return $this->limiter->attempt(
            fn () => $this->breaker->call(fn () => Http::timeout(10)->retry(3, throw: false)->send($r)),
        );
    }
}
```

A call with no timeout is an outage waiting for a slow Tuesday. `retry(..., throw: false)` is
deliberate — surface the failure rather than hiding it behind an exception nobody catches.

### 11.7 Scramble

```php
// AppServiceProvider::boot()
Scramble::configure()
    ->withDocumentTransformers(function (OpenApi $openApi) {
        $openApi->info()->title(config('app.name'))->version('1.0.0');
    })
    ->exportsOpenApiUsing(fn () => ['api.json' => public_path('api.json')]);
```

Regenerate with `php artisan scramble:export` after any controller or DTO change. Never hand-edit.

### 11.8 Action — one operation, one public method

```php
namespace Modules\Booking\Application\Actions;   // FLAT module: Modules\Booking\Actions

final class CancelBookingAction
{
    public function execute(CancelBookingData $data): Booking
    {
        return DB::transaction(fn (): Booking => /* one aggregate */);
    }
}
```

- **One public method named `execute()`** — no `__invoke`, no interface, no command bus. Three actions
  called from one controller is the correct amount of ceremony (`SKILL-ARCHITECTURE.md` §6).
- A one-step CRUD operation with no invariant and no second write does not need an Action: the
  controller calls the port directly. A `DeleteLeadAction` that only forwards
  `LeadRepository::delete()` is a dumb pass-through (`DesignSmellsTest`) and violates YAGNI.
- `DB::transaction` wraps multi-write operations only — never an HTTP call, never a `dispatch()`.
- The controller maps the result (`{Entity}Data::from()`); an Action never builds a response and never
  receives a `Request`.
- PROGRESSIVE only: dependencies are **ports bound by the module provider** — an Action never `new`s an
  adapter. A FLAT action takes the model directly.

### 11.9 FormRequest — transport, not business

```php
final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('CREATE_BOOKINGS') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'starts_at' => ['required', 'date', 'after:now'],
        ];
    }
}
```

- `authorize()` checks the **same permission family the route middleware uses**
  (`permission:CREATE_BOOKINGS` on the route, `CREATE_BOOKINGS` on the Gate): defence in depth, never a
  second definition of a business rule.
- One request per verb — `Store`, `Update`, `BulkDelete`, `BulkRestore`. Bulk verbs carry only `uuids`
  (`required|array|min:1|max:500`, each `uuid`); the shared payload contract is
  `Shared\Application\DTOs\BulkUuidsData`.
- Query filters validate through `{Entity}FilterData` (Spatie Data), never a FormRequest.
- Array filters whose items are checked against the database (`exists`, `unique`) carry a `max:`
  cap (e.g. `['nullable', 'array', 'max:50']`). An uncapped array is N validation queries in one
  request — OWASP API4 — even when `distinct` will ultimately reject the duplicates.
- Business invariants live in the Action or the domain; `rules()` stays transport-only.

### 11.10 Pest — the quartet, then a DB-free unit test

```php
uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);   // module Feature test header

it('redirects guests to login', fn () => get('/bookings')->assertRedirect('/login'));
it('refuses a JSON write for a guest with 401', fn () => $this->postJson('/bookings', [])->assertUnauthorized());
it('refuses a user without the permission with 403', fn () => actingAs(User::factory()->create())->getJson("/bookings/{$row->uuid}")->assertForbidden());
it('rejects invalid input with 422', fn () => actingAs($this->user)->postJson('/bookings', [])->assertStatus(422));
```

```php
it('cancels without the database', function (): void {
    $this->mock(SlotRepository::class)->shouldReceive('overlapping')->once()->andReturnFalse();

    app(CancelBookingAction::class)->execute(new CancelBookingData(...));
    // PROGRESSIVE: assert on the port, not on the ORM — invariant 15
});
```

- Every module Feature test covers **401, 403 and 422** plus the happy path. The generator emits the full
  matrix (ownership, bulk pair, exports) — never hand-write a subset.
- A PROGRESSIVE module ships **at least one DB-free test with a mocked port**; `$this->mock()` needs the
  `Tests\TestCase` binding shown above.
- Arch rules live in `tests/Architecture/`; a stray `dd`/`dump`/`ray` anywhere fails them.
