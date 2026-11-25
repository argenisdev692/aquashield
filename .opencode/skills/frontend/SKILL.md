---
name: frontend
description: React 19.3 + Inertia v3 + strict TypeScript rules — React Compiler, mandatory 19.x APIs, shadcn/ui on Base UI, TanStack Query server state, TanStack Table v9, Tailwind v4 token-first styling, Zod forms, FullCalendar v7 React, Motion, and the security checklist. For file placement see frontend-architecture.
---

# FRONTEND — React 19.3 + Inertia v3 + shadcn/ui

> File placement and layers → `frontend/SKILL-ARCHITECTURE.md`.
> Security → `owasp/SKILL.md`.

**Stack (verified 2026-11-18 — installed pins, all on the current release):** React **19.3.1** (latest stable 19.x) ·
React DOM 19.3.1 · Inertia **3.11.0** client / **3.4.0** server · TypeScript **7.0.2** strict (`tsc`, native
Go — see §4) · Tailwind **4.3.5** (`@tailwindcss/vite`, no config file) · shadcn CLI **4.23.0** with
**Base UI 1.9.0** (`components.json` style `base-nova`) · TanStack Query **5.108.0** · TanStack Table **9.3.1** ·
React Compiler **1.0.0** (`babel-plugin-react-compiler`) · Zod **4.6.7** · FullCalendar React **7.1.5** ·
Motion for React (`framer-motion`) **14.14.0** · Vite **8.3.8** · lucide-react **1.74.0**.

> Patch/minor drift on 2026-11-18 is a dependency-upgrade task, not a docs edit. Bump deliberately
> (`npm update <pkg>` + `npm run module:done`), never as a side effect of a feature change.

> **React version policy.** 19.3 is the current stable line and the 19.x API set is mandatory. 19.2
> stabilised `<Activity>` and `useEffectEvent`; 19.3 stabilised `<ViewTransition>`,
> `addTransitionType`, Fragment refs and `browser()`. Do not pin an exact patch in application code —
> patches move. A 20.x upgrade is a deliberate, dated migration, not a side effect of `npm update`.

> **Vue 3.5 is not the stack.** The Vue 3.5 + Pinia Colada + PrimeVue/Volt app was replaced by React
> 19.3 + TanStack Query + shadcn/ui. No `.vue` file, `composables/` hook directory, `pinia`,
> `@pinia/colada`, `primevue/*`, `@primevue/*`, `tailwindcss-primeui`, `src/volt/` or `motion-v`
> import is valid in new code. Those are migration leftovers: port the behaviour, do not extend the
> Vue shape. The UI layer is shadcn/ui source code in `resources/js/Components/ui/` on Base UI.

---

## 0. Token-first styling

Never hardcode a colour. No hex, no `bg-red-600`, no `bg-[#1b1b18]`. Use a semantic token class
(`bg-surface-0`, `text-surface-700`, `bg-aq-elevated`, `text-aq-ink-soft`) or `var(--token)`.

**`resources/css/app.css` must keep this import order:**

```css
@import 'tailwindcss';
@import './globals.css';
@import 'tw-animate-css';
@import 'shadcn/tailwind.css';
@import '@fontsource-variable/geist';
@custom-variant dark (&:is(.dark *));
```

`@source` must cover `../**/*.tsx` and `../../src/**/*.tsx`. Tailwind v4 has no
`tailwind.config.js` and no PostCSS pipeline here; `@theme` in `globals.css` is the only token
mapping.

`globals.css` holds the brand ramp (`--color-aq-*`), the surface bridge (`--p-surface-*` →
`--color-surface-*`, retained from the PrimeUI token names), dark overrides and the `tabular-nums`
utility. A call site that needs a new elevation, glow or mix gets a token there — not a bracket in
a component.

## 1. Dark mode

The `.dark` class on `<html>`, and nothing else. No `data-theme`, no `.light`.

- The first-paint script in `resources/views/app.blade.php` is authoritative: it reads `app:theme`
  and writes `.dark` before the stylesheet.
- `useTheme()` in `resources/js/lib/theme.ts` owns persistence afterwards. It is the only source of
  truth; do not add a second theme store.
- Dark-first is the project policy: no stored preference means dark. If you change that default,
  change the blade script and `lib/theme.ts` together or the page flashes.
- The storage key is `app:theme`. `app:theme` and `theme` are different stores.

## 2. Accessibility — WCAG 2.2 AA

Every interactive element reachable by keyboard, with a visible focus ring that is never removed.
Every image has alt text. Every form field has a `<label for>`. Every icon-only control has
`aria-label`. Dialogs trap focus and restore it on close. Colour contrast ≥ 4.5:1 for body text.

`useId()` generates any id used in an `aria-*` relationship — never a random string, never an array
index. Base UI primitives already implement focus management; do not re-implement it around them.

## 3. React 19.3 — mandatory APIs

```tsx
const uid = useId(); // every generated id
const inputRef = useRef<HTMLInputElement>(null); // ref is a normal prop in 19
const onConnected = useEffectEvent(() => notify(theme)); // latest values, not a dependency
const user = use(userPromise); // only for a promise already in flight
```

- **Function components only.** No class components, no legacy lifecycles.
- **`ref` is a prop.** Do not write `forwardRef` in new code. A callback ref may return a cleanup
  function.
- **`useEffectEvent`** separates an event from the effect that subscribes to it. Never put the
  event function in the dependency array.
- **`<Activity mode={visible ? 'visible' : 'hidden'}>`** hides and restores UI state instead of
  unmounting it. Use it for tab panes and keep-alive panels — not as a substitute for routing.
- **`<ViewTransition>` + `addTransitionType()`** (19.3) animate enter/exit/update/share, but only
  updates inside `startTransition`, a Suspense reveal or `useDeferredValue` animate.
- **Scheduling:** `useTransition` / `useDeferredValue` keep typing, filtering and tab switches
  responsive. Use `startTransition` for a filter change so the current rows stay visible while the
  next result loads (`isPending` drives the inline busy state).
- **`useSyncExternalStore`** is only for a genuine external store (a browser API, a third-party
  subscription). It is not a replacement for TanStack Query or local state.
- **Fragment refs** (`<Fragment ref={ref}>`) give a `FragmentInstance` with `focus()`,
  `observeUsing()`, `getClientRects()` without a wrapper element.
- **`useOptimistic` / `useActionState` / `useFormStatus`** are for form actions. Inertia owns the
  request in this app (§5); use them only in a genuinely local, non-Inertia flow.
- **Never mutate props, state or values captured by render.** The compiler depends on the Rules of
  React, and so does correctness.

### React Compiler

The compiler is enabled in `vite.config.js`:

```js
react({ babel: { plugins: ['babel-plugin-react-compiler'] } });
```

- It memoises components and hooks automatically. **Do not add `useMemo` / `useCallback` by
  reflex.** Add one only when referential identity is an external contract (a third-party
  subscription, a dependency of a non-React API).
- Write compiler-friendly code: pure render, no mutation after render, no reading a ref during
  render, no conditional hooks.
- `'use no memo'` is an escape hatch for one component with a written reason. It is not a pattern.
- `react-compiler-runtime` is only required for `target: '17' | '18'`; this app targets React 19,
  so the compiler uses the built-in runtime. Do not install it here.

## 3.1 Suspense, streaming and skeletons

React 19.3 has a small set of legitimate loading paths. Pick by intent, never by habit:

| Situation                                                 | API                                               | What the user sees                                       |
| --------------------------------------------------------- | ------------------------------------------------- | -------------------------------------------------------- |
| The panel is meaningless until its data arrives           | `useSuspenseQuery` inside `<Suspense>`            | `<Skeleton>` shaped like the final panel                 |
| A filter, page or sort change over data already on screen | `useQuery` + `placeholderData: keepPreviousData`  | Previous rows stay; inline `isFetching` indicator        |
| A secondary panel below the fold or behind a tab          | `usePrefetchQuery` / `queryClient.prefetchQuery`  | Primary content renders first; the panel fills on reveal |
| Inertia deferred prop (`Inertia::defer` / `optional`)     | `<Deferred data="…" fallback={<Skeleton>}>`       | Skeleton in the deferred slot                            |
| A lazily imported heavy widget (calendar, 3D, chart)      | `React.lazy` + `<Suspense fallback={<Skeleton>}>` | Skeleton at the widget's exact dimensions                |

```tsx
// Modules/{context}/hooks/use{Context}.ts — one options factory, reused by query, prefetch and suspense.
export const listQuery = (params: URLSearchParams) =>
  queryOptions({
    queryKey: ['{context}', 'list', params.toString()],
    queryFn: ({ signal }) => fetchList(params, signal),
    staleTime: 30_000,
    gcTime: 300_000,
  });

// Page — the hook owns the key and the fetcher; the page owns only the boundary.
function UsersPanel({ params }: { params: URLSearchParams }) {
  const { data } = useSuspenseQuery(usersListQuery(params));
  return <UserTable rows={data.rows} total={data.total} />;
}

<Suspense fallback={<Skeleton className="h-80 rounded-xl" />}>
  <UsersPanel params={params} />
</Suspense>;
```

- **One boundary per panel, not one at the app root.** A root-only boundary turns a slow secondary
  query into a blank page. Scope `<Suspense>` around the thing that is actually waiting.
- **A skeleton mirrors the final layout.** Same number of columns/rows, same heights, same grid.
  `Components/ui/skeleton.tsx` is the primitive; table, card and panel skeletons compose it.
  No spinner-only full page, and no layout shift when the real content replaces the placeholder.
- **`useSuspenseQuery` has no `enabled`.** Data is `T`, never `T | undefined`, and errors go to the
  nearest error boundary. Compose a child component instead of disabling the query.
- **Prefetch before the boundary, not inside it.** `usePrefetchQuery(options)` during render of the
  parent, or `queryClient.prefetchQuery(options)` on hover/navigation. Inertia page props are
  already server-rendered; prefetch only the follow-up reads.
- **Do not wrap a filter change in Suspense.** Use `useQuery` + `placeholderData` and
  `startTransition` for the state update, so the current rows stay visible while the next page
  loads.
- **Errors need a boundary, not `throwOnError`.** `useSuspenseQuery` has no `throwOnError` option in
  v5 (`UseSuspenseQueryOptions` omits `throwOnError`, `enabled` and `placeholderData`); its errors
  always propagate to the nearest error boundary. Wrap the panel in one and use
  `QueryErrorResetBoundary` to reset a failed query. `retry: 1` is the global default; do not retry
  a 4xx. `throwOnError` applies only to non-suspense `useQuery`.
- **Never mirror query data into `useState`.** Suspense, `placeholderData` and the cache already
  cover loading, stale and error states.

## 4. TypeScript

`strict: true`, `noEmit`. **Zero `any`** — no `any`, no `as any`, no implicit `any`, no
`@ts-ignore`. Use `unknown` plus a narrowing guard.

**TypeScript 7.0.2 is the project compiler** — the native Go port (GA 2026-07-08), same type system
as 6.0, typically 8–12× faster. Everything deprecated in 6.0 is a hard error here (`baseUrl`,
`moduleResolution: node`, `target: es5`, AMD/UMD, `esModuleInterop: false`, …); this tsconfig uses
none of them, and `target` stays `ES2022`.

7.0 ships **no programmatic API**. Tooling that imports the compiler (typescript-eslint, Vue/Svelte/
Astro template checkers, ts-morph codemods) needs TypeScript 6 until 7.1 lands that API — use the
`npm:@typescript/typescript6` alias only when such a tool is actually added. The gate itself is
`npx tsc --noEmit`, which is native and needs no alias. Do not add a tool that requires the API
without wiring that alias first.

`forceConsistentCasingInFileNames` is `false` on this Windows-only repo, which is why
`@/components/ui/...` resolves to `resources/js/Components/ui`. Do not rely on that: write the
path as the directory is spelled on disk when you touch it.

**Backend types are generated, never hand-written.** `php artisan typescript:transform` writes
`resources/js/generated/generated.d.ts` from the Spatie `Data` classes. Hand-writing an interface
that mirrors a `Data` class is how `any` and silent drift get in. Run it after any backend DTO or
enum change.

## 5. Inertia 3

```tsx
// resources/js/app.tsx
createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx');
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) throw new Error(`Inertia page not found: ${name}`);
    return page().then((module) => module.default);
  },
  setup({ el, App, props }) {
    createRoot(el).render(
      <QueryClientProvider client={queryClient}>
        <App {...props} />
      </QueryClientProvider>,
    );
  },
});
```

Never use `eager: true` for page globs — it puts every page in the entry chunk. Pages are `.tsx`
and the module is `{ default: Component }`.

Navigation is `<Link>`. `router.visit(url, { preserveScroll: true })` for a page visit;
`router.optimistic((props) => ({...}))` before the post for an optimistic update. Deferred props
use `Inertia::defer(fn () => ...)` on the server and the `<Deferred data="…" fallback rescue>`
component on the client. `useHttp()` from `@inertiajs/react` is for plain JSON calls that are not
page visits — **but network I/O belongs in a module hook, not a page** (architecture invariant 1).

A page visit is not a TanStack mutation. `useVisitPending()` from `lib/visits.ts` subscribes to
`router.on('start'/'finish')`; use it to disable table controls and show skeleton rows while the next
Inertia page is in flight. Do not hardcode `loading={false}`.

**v3 breaking changes** (v3.0.0 is GA):

- Axios is **no longer a required dependency** — Inertia ships its own XHR client. Install Axios
  only if you need interceptors, and wire it through `axiosAdapter` from `@inertiajs/core`.
- `qs` and `lodash-es` are no longer bundled. Install `qs` directly if you import it.
- `Inertia::lazy()` was **removed** → `Inertia::optional()`. `optional()`, `defer()` and `merge()`
  still exist and work inside nested arrays with dot-notation.
- `router.cancel()` was **removed** → `router.cancelAll()`.
- The v2 `future` block is gone — all v2 future flags are always on.
- **ESM-only.** CommonJS `require()` no longer works, and **Node ≥ 24** is required.

Also in v3: the default layout can be set in `createInertiaApp` plus a layout-props API;
`resolve(name, page)` receives the full page object; `useDataAttributeForInitialPage`;
`preserveErrors` keeps validation errors across partial reloads; instant visits. **This project
renders `<AppLayout>` explicitly inside each page** — do not switch to `Page.layout` without a
repo-wide migration.

**SSR is off** (`INERTIA_SSR_ENABLED=false`); there is no `bootstrap/ssr/` bundle.

## 6. shadcn/ui on Base UI

Components are **source code you own** in `resources/js/Components/ui/`, generated by the CLI:

```bash
npx shadcn@latest add button dialog select table
```

`components.json` is the contract: style `base-nova`, Base UI primitives, `lucide` icons, CSS
variables, aliases `@/components`, `@/components/ui`, `@/lib/utils`.

- **Base UI is the default since July 2026.** New primitives import from `@base-ui/react/*`;
  Radix is not installed. Composition uses Base UI's `render` prop, **not** Radix's `asChild`:
  `<Button render={<Link href="/users" />}>Users</Button>`.
- **`Skeleton` is the loading primitive.** Add it with `npx shadcn@latest add skeleton`; compose
  table, card and panel skeletons from it. Do not hand-roll `animate-pulse` divs at a call
  site — the primitive carries `motion-reduce` and the token background.
- Never install `radix-ui`, `@radix-ui/*`, `primevue`, `@primevue/*`, `pinia`, `@pinia/colada` or
  a second component library. If a primitive is missing, compose the existing ones or add the
  shadcn component with the CLI.
- Variants live in the primitive (`cva` + `buttonVariants`). Do not re-style a primitive with a
  long `className` at every call site; edit the primitive — that is the point of code ownership.
- `cn()` is the class merge helper (`clsx` + `tailwind-merge`). App code imports it from
  `@/lib/utils`; the generated primitives use the `cn` package.
- Icons are `lucide-react`. Decorative icons get `aria-hidden="true"`; icon-only buttons get an
  `aria-label`.
- Dialogs, sheets, popovers and tooltips are Base UI portals. Do not wrap them in your own focus
  trap or escape handler.

## 7. TanStack Query v5.108 — client-owned server state

**Choose the transport before writing the screen.** This repo has two legitimate server-state
transports, and mixing them for the same endpoint is the one thing that is always wrong:

| Data belongs to                                                                   | Transport                                        | Loading UI                       |
| --------------------------------------------------------------------------------- | ------------------------------------------------ | -------------------------------- |
| A page visit (list, show, edit)                                                   | Inertia props + `router.visit` / partial reloads | `useVisitPending()` + `Skeleton` |
| A panel that outlives the visit (polling, live inbox, dashboard widget, prefetch) | TanStack Query in `Modules/{ctx}/hooks/`         | `useQuery`/`useSuspenseQuery`    |

Do **not** call `useQuery` for a list whose data already arrived as an Inertia prop: that fires the
same request twice and creates two caches for one screen. Do **not** model a page visit as a
mutation. TanStack Query is for client-owned async state, not a replacement for Inertia routing.

`QueryClientProvider` is mounted once in `app.tsx`, with a global `staleTime`/`gcTime`/`retry`
default. Module hooks own the key, the fetcher and the cache policy; they export a **`queryOptions()`
factory** so the same definition is reused by `useQuery`, `useSuspenseQuery` and prefetching.

```ts
// Modules/SavedViews/hooks/useSavedViews.ts — the client-owned panel pattern.
import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

export const savedViewsQuery = (resource: string) =>
  queryOptions({
    queryKey: ['saved-views', resource],
    queryFn: ({ signal }) => fetchSavedViews(resource, signal),
    staleTime: 60_000,
    gcTime: 300_000,
  });

export function useSavedViews(resource: string) {
  const queryClient = useQueryClient();
  const list = useQuery(savedViewsQuery(resource));

  const save = useMutation({
    mutationFn: (input: SaveSavedViewInput) => postSavedView(resource, input),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['saved-views', resource] }),
  });

  return { views: list.data ?? [], save };
}
```

A paginated list keeps `placeholderData: keepPreviousData` (or `(previous) => previous`) and invalidates
the matching **prefix** on `onSettled`, so every cached page and filter combination refetches.

- **Use `useSuspenseQuery` when the panel needs data to exist**, with a `Skeleton` fallback (§3.1).
  Use `useQuery` + `isPending` for the first load of a panel that can render its own empty state.
- **Prefetch with the same factory:** `queryClient.prefetchQuery(usersListQuery(params))` or
  `usePrefetchQuery(usersListQuery(params))`. Never duplicate a key or fetcher at a call site.
- **Seed Inertia's first render with `initialData`**, not a render-time `setQueryData`: a write
  during render is a side effect and React Compiler/Strict Mode will expose it. `initialData` plus
  `initialDataUpdatedAt` keeps the server-rendered row fresh for `staleTime`, then refetches.
- **Never mirror query data into `useState`, a context or a store.**
- Inertia writes that redirect (create/update/delete) do not go through `useMutation`, so the page
  calls the module's invalidation helper after the visit.
- `onSuccess` / `onError` are not available on `useQuery` in v5. Put side effects in the event
  handler, the mutation callbacks, or an effect that reads the result. For suspense queries the
  error is thrown to the nearest error boundary automatically — `throwOnError` is not an accepted
  option there; use `QueryErrorResetBoundary` to retry.
- `isPending` is the first-load flag; `isFetching` is the background-refetch flag. Do not show a
  full skeleton for `isFetching` when rows are already on screen.

## 8. TanStack Table v9 — the table engine

v9 is **ESM-only**, targets ES2022, and changes how a table is declared: `useReactTable` is now
`useTable`, and features, row models and `sortFns`/`filterFns` are registered on a required
`features` option.

```tsx
import { useTable, tableFeatures, rowSortingFeature, createSortedRowModel, sortFn_text } from '@tanstack/react-table';

const features = tableFeatures({
  rowSortingFeature,
  sortedRowModel: createSortedRowModel(),
  sortFns: { text: sortFn_text },
});

const table = useTable({ features, columns, data });
```

Rules for this repo:

- **Server-first.** Lists pass `manualPagination`, `manualSorting` and `manualFiltering`, and do
  **not** register client row models — the server already paginated. The browser never sorts,
  filters, slices or paginates the dataset it received. A component that calls `[...data].sort()`
  or `data.slice()` on server rows is a correctness bug, even if it looks right for page 1.
- The table kit in `Components/DataTable/` is the app-level composition: toolbar, search, filters,
  bulk bar, export and empty state. It renders `data` in the order given and uses `total` only for
  the page count; its sort/pagination controls emit state/URL changes, never local reordering. Use
  its `SimpleColumn<T>` contract for a plain server table; use `useTable` directly when you need
  column definitions, pinning, selection or spanning.
- Call instance methods on the instance (`row.getValue('name')`, `cell.getContext()`). v9 puts them
  on the prototype; destructuring loses `this`.
- Read state from `table.state` (or `table.store.state`). `table.getState()` is v8. Use
  `table.Subscribe` / `table.atoms.<slice>` only when a large table actually needs narrow renders.
- `flexRender(...)` still exists; `<table.FlexRender cell={cell} />` is the v9 component form.
- `createColumnHelper<typeof features, TData>()` — the features generic is first. `ColumnDef`,
  `Row`, `Cell` and `Table` all take `TFeatures` first.
- Sorting is `sortFn`, not `sortingFn`; pinning is `start` / `end`, not `left` / `right`;
  `columnSizingInfo` is `columnResizing`.
- **`stockFeatures`** reproduces v8 behaviour. Acceptable for a migration, not for new code — it
  defeats the tree-shaking that v9 exists for.

```tsx
const features = tableFeatures({ rowPaginationFeature });

const table = useTable({
  features,
  columns,
  data: query.data?.rows ?? [],
  rowCount: query.data?.rowCount,
  state: { pagination, sorting },
  onPaginationChange: setPagination,
  onSortingChange: setSorting,
  manualPagination: true,
  manualSorting: true,
  manualFiltering: true,
});
```

## 9. Filters and exports — one contract

Search (debounced, minimum length) · status select · date range. Compose them with
`lib/table.ts` (`buildTableParams`, `buildTableUrl`, `parseTableParams`) and `useCrudTable` from
`lib/crud-table.ts` into the **single** URL shape the server reads. `CrudFilters` renders the bar from
a `CrudFilterDef[]`; the page never builds a query string by hand. The URL is the state: reload,
back/forward and a saved view all resolve to the same table. The query that fills the table and the
export endpoint must be built from the same state (`exportUrl()` uses `paginate: false`), so a
filtered export exports exactly what is on screen. Validate `date_from <= date_to` before sending
(`CrudFilters` clears the opposite bound instead of submitting an inverted range).

## 10. Feedback and notifications

There is no second notification system. Use inline `FormErrorSummary` / field errors for form
failures and a Base UI `Dialog` for destructive confirmation. If a screen genuinely needs toasts,
that is a dependency decision (see `rules.md`): mount **one** provider in `AppLayout` and never a
second. Never use `window.confirm()` or `window.alert()`.

## 11. Forms

One path: `useZodForm` from `@/lib/forms` + the field components from `@/Components/Forms`. Inertia
owns the request and the 422 bag; Zod owns the client schema; `errors` merges both (server wins).

> **Inertia has its own `useForm` — it is not React Hook Form.** `@inertiajs/react` ships
> `data`/`setData`/`errors`/`processing`/`post`/`put`/`patch`/`delete`/`optimistic`. Do not add
> `react-hook-form` as a third engine. If the project deliberately moves to a richer form engine,
> the reference pattern is TanStack Form + Standard Schema (Zod 4 passes through) wrapping
> `router.visit`, as in `resources/js-argenis-hub/common/form/useAppForm.ts`; then replace
> `useZodForm`, never run both.
>
> **React actions are not Inertia forms.** `useActionState` / `useFormStatus` / `<form action>` do not
> apply to Inertia page visits; use `form.processing`, `form.errors` and `form.optimistic()`. Use
> React's `useOptimistic` only in a genuinely local, non-Inertia action flow.

```ts
const { form, errors, submit, touch, clearError } = useZodForm({
    initial: { email: '', remember: false },
    schema,
});

<form onSubmit={(event) => { event.preventDefault(); submit(() => form.post('/login')); }}>
```

- **Inertia v3's `useForm` takes the values object as its first argument** (`useForm(initial)`), not
  an options bag. `useZodForm` already wraps that; do not call `useInertiaForm({ data: … })`.
- Schemas live in `Modules/{ctx}/schemas/` and export the schema plus a `z.infer<>` type.
- Use a kit field (`TextField`, `PasswordField`, `SelectField`, `MultiSelectField`,
  `OtpField`, `DateField`, `PhoneField`, `NumberField`, `TextareaField`, `CheckboxField`,
  `AvatarCropperField`, `AddressAutocompleteField`, `SignatureField`) rather than a bare shadcn
  input: it wires
  `label for`, `aria-describedby`, `aria-invalid` and a `role="alert"` error.
- `FormErrorSummary` goes at the top. `submit(action)` validates, focuses the first
  `[aria-invalid="true"]` control, and only then runs the Inertia call.
- Modal/sheet forms use `FormDialog` / `FormSheet` from `Components/Forms`; they own the native
  `<form>`, submit/cancel row and dirty-close guard. Pass the `useZodForm` `form`, `errors` and
  `onSubmit`; do not hand-wire a `Dialog` around a form.
- Never hand-roll `safeParse` in a submit handler, and never rely on browser bubbles.
- Uploads: `FileDropzone`, `ImageDropzone`, `ImagesDropzone`. They revoke object URLs and the
  gallery reorders with native drag **and** keyboard buttons.
- `AppButton` is the submit control: hover sheen + 1px lift, `prefers-reduced-motion` safe.

## 12. FullCalendar 7 — React connector

v7 removed the standalone plugin packages. Views are **subpath exports of the React connector**,
and `temporal-polyfill` is a required peer.

```tsx
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/react/daygrid';
import timeGridPlugin from '@fullcalendar/react/timegrid';
import interactionPlugin from '@fullcalendar/react/interaction';
import type { DateSelectInfo } from '@fullcalendar/react';
```

The v7 type names are `DateSelectInfo`, `DateClickInfo`, `EventClickInfo` — not the old
`DateSelectArg` / `DateClickArg`.

Never install `@fullcalendar/daygrid`, `@fullcalendar/timegrid` or the Vue connector. Never install
`motion-v` — that is the Vue package. Resource/scheduler views are a separate **premium** package;
check the v7 pricing page before adding one.

v7 renamed: `eventClassNames` → `eventClass`; `eventBackgroundColor` / `eventBorderColor` →
`eventColor`; `eventTextColor` → `eventContrastColor`; DayGrid `weekNumber` → `inlineWeekNumber`;
TimeGrid `weekNumber` → `weekNumberHeader`; `allDay` → `allDayHeader` in TimeGrid.

The calendar is the one screen with genuinely different read and write profiles — a `Queries/`
class for the month grid, an action for the booking. See `backend-php/SKILL-ARCHITECTURE.md` §4.

## 13. Animation

- **CSS transitions first** for hover, focus and state colour. Every interactive state carries a
  `transition-*` and a duration (the architecture test enforces this).
- **`framer-motion` 14 (Motion for React)** for layout animation, gestures, scroll-linked work and
  stagger. Shared variants live in `lib/motion.ts`; never inline a variant in a component.
- Load motion-heavy routes/scenes lazily with `React.lazy` + `<Suspense>`.
- Never animate `background-color` or `font-size` — transition a token in CSS instead.
- Never exceed 0.4 s. Never scale past 1.04 on hover. Respect `prefers-reduced-motion` via
  `useReducedMotion()` — every variant in `lib/motion.ts` already branches on it.
- `<ViewTransition>` is for React-driven enter/exit/share animations inside `startTransition`. Do
  not stack it on the same element as a Motion `AnimatePresence` exit.
- The export is `AnimatePresence`, not `Presence`.

## 14. Security

The frontend is not a trust boundary. Every authorisation decision is re-checked on the server;
hidden UI is a courtesy, never a control. Render capabilities from the server props
(`canCreate`, `canUpdate`, `canDelete`, `authPermissions`), never from a role string inferred in
the browser. Never expose secrets, internal ids or raw error text.

React escapes text by default. **`dangerouslySetInnerHTML` is forbidden on user input**; if rich
text is unavoidable, sanitise it on the server, keep a comment saying so, and add a CSP test.
Never `eval()` or `new Function(string)`. Lock down the CSP in `config/csp.php` — no
`unsafe-eval` in production.

**CSP and inline boot code need a nonce.** Tailwind ships static CSS, but the first-paint theme
script and Vite's dev bootstrap are inline, and Base UI portals can write inline `style`
attributes. `style-src 'self' 'unsafe-inline'` is the easy path and it is forbidden in production —
use a per-request nonce (OWASP §5, §15.5):

```blade
{{-- resources/views/app.blade.php --}}
{{-- Nonce wiring (single source: spatie/laravel-csp `csp-nonce` binding).
    `@cspNonce` prints it in Blade (there is NO `csp_nonce()` helper).
    `Shared\Infrastructure\Http\Middleware\SecurityHeaders` (prepended in
    bootstrap/app.php) syncs it into `@vite` tags per request AND emits the
    CSP/OWASP headers. Never re-bind `csp-nonce`, never call
    `Vite::useCspNonce()` with no args (random nonce ≠ header nonce). --}}
<script @cspNonce>
    /* FOUC-killer / inline bootstrap code */
</script>
@vite(['resources/css/app.css', 'resources/js/app.tsx'])
```

If you cannot ship a nonce, the acceptable fallback is `style-src 'self' 'sha256-…'` with hashes
extracted from the Vite manifest — **not** `'unsafe-inline'`. Document any deviation with a
`Justified-OWASP-Deviation:` note. React 19.3 also passes Trusted Types objects through to the DOM
unchanged, so a `require-trusted-types-for 'script'` policy is compatible.

### Theme controller

`.dark` on `<html>` is the single source. The toggle writes it; nothing else may. Inline the read in
`app.blade.php` `<head>` **before** the stylesheet or the page flashes light on load:

```html
<script @cspNonce>
  var stored = localStorage.getItem('app:theme');
  var theme = stored || 'dark';
  if (theme === 'dark') document.documentElement.classList.add('dark');
  document.documentElement.style.colorScheme = theme;
</script>
```

The canonical copy lives in `resources/views/app.blade.php` — `@cspNonce`, not `csp_nonce()` (no
such helper in spatie/laravel-csp v3). The storage key is `app:theme`, matching `useTheme()` in
`resources/js/lib/theme.ts`; a `theme` key is a different store and will not persist.

**Dark-first is the project policy:** no stored preference means dark. Do not switch the default to
system without changing the blade first-paint script and `lib/theme.ts` together, or the page
flashes.

## 15. Checklist before merging a page

- [ ] `<Head title>` present and the page renders `<AppLayout>`
- [ ] No `any`, no `@ts-ignore`, types generated not hand-written
- [ ] React 19 APIs used correctly (`useId`, `ref` as prop, `useEffectEvent`, `<Activity>`, `use()`)
- [ ] React Compiler is not fought: no reflexive `useMemo` / `useCallback`, no mutation after render
- [ ] One server-state owner per screen: Inertia props for a page visit, TanStack Query only for a
      client-owned panel; no endpoint fetched twice
- [ ] TanStack Query panels use `queryOptions` with `staleTime` + `gcTime`; lists use
      `placeholderData: keepPreviousData`
- [ ] Every waiting panel has a `<Suspense>` boundary and a `Skeleton` that matches the final
      layout; no spinner-only full page and no layout shift
- [ ] Table is server-side (`manualPagination` / `manualSorting` / `manualFiltering`); the browser
      does not sort, slice, filter or paginate rows received from the server
- [ ] Destructive action confirmed via a Base UI `Dialog`; both delete and restore shipped; no
      `window.confirm()` / `window.alert()`
- [ ] Forms use `useZodForm` + the kit; every error is wired with `aria-describedby` and
      `role="alert"`
- [ ] Uploads use the dropzone kit; object URLs are revoked; reorder is keyboard reachable
- [ ] No hardcoded colours and no raw Tailwind palette classes
- [ ] Keyboard reachable, labelled, focus ring intact
- [ ] `npm run lint`, `npm run types:check` and `npm run format:check` clean
