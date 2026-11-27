---
name: frontend-architecture
description: Directory tree, layer boundaries and file placement for the React 19 + Inertia v3 frontend. Covers the @ alias resolution, shadcn/ui primitives in Components/ui, the layer import matrix with its verifiable invariants, module hook placement, and where server and client state live.
---

# FRONTEND ARCHITECTURE — directory tree and layers

> Rules, patterns and APIs → `frontend/SKILL.md`.
> Security → `owasp/SKILL.md`.

## 1. The tree as it actually is

```
src/
├── Modules/                 backend modules (empty today)
└── Shared/                  PHP only: ports, adapters, DTOs
                             (see backend-php/SKILL-ARCHITECTURE.md §5)

resources/
├── css/
│   ├── app.css              Tailwind v4 entry — import order and @source are load-bearing
│   └── globals.css          ALL design tokens: --color-aq-*, --color-surface-*, dark overrides
├── js/
│   ├── app.tsx              createInertiaApp + QueryClientProvider
│   ├── Components/
│   │   ├── ui/              shadcn/ui source on Base UI — CODE-OWNERSHIP, committed
│   │   │                    (button, dialog, table, skeleton, …)
│   │   ├── DataTable/       server-first table kit + CrudDialog + export/filters
│   │   └── Forms/           useZodForm field kit, buttons, dropzones
│   ├── Layouts/             AppLayout + AuthLayout
│   ├── lib/
│   │   ├── utils.ts         cn() — clsx + tailwind-merge
│   │   ├── forms.ts         useZodForm — Inertia + Zod, error merge + focus
│   │   ├── http.ts          xsrfToken() for plain fetch calls
│   │   ├── files.ts         file validation and byte formatting
│   │   ├── phone.ts         libphonenumber-js formatting + phoneSchema
│   │   ├── table.ts         one query contract for table + export
│   │   ├── visits.ts        useVisitPending() — global Inertia visit state
│   │   └── theme.ts         the single theme/sidebar store
│   ├── Modules/{Context}/
│   │   ├── hooks/           THE ONLY network boundary (invariant 1)
│   │   ├── schemas/         Zod schemas + z.infer<> types
│   │   └── components/      optional module-private UI
│   ├── Pages/               Inertia pages — usePage() + useZodForm()
│   │   ├── Auth/            Login, Register, ForgotPassword, ResetPassword,
│   │   │                    VerifyEmail, ConfirmPassword, TwoFactorChallenge
│   │   ├── Dashboard/
│   │   └── {Context}/       Index, Show, Create, Edit (+ page-private components/)
│   └── generated/           generated.d.ts from `typescript:transform`
└── views/
    ├── app.blade.php        Inertia root — @inertia, @inertiaHead, @vite, theme nonce
    └── errors/
```

**There is no `src/volt/` and no `resources/js/types/`.** The type surface is
`resources/js/generated/generated.d.ts` plus ambient `*.d.ts` files placed next to the code that
needs them. If a shared type appears twice, that is a `Data` class missing from
`typescript:transform`, not a folder to create.

## 2. The `@` alias — and why it is not one rule

Vite aliases use **first string-prefix match with no filesystem check**, and a `find` ending in `/`
never matches (the resolver appends the slash itself). The mapping is split by prefix:

```js
// vite.config.js
resolve: {
    alias: [
        { find: '@/pages',   replacement: fileURLToPath(new URL('./resources/js/Pages/', import.meta.url)) },
        { find: '@/layouts', replacement: fileURLToPath(new URL('./resources/js/Layouts/', import.meta.url)) },
        { find: '@',         replacement: fileURLToPath(new URL('./resources/js/', import.meta.url)) },
    ],
},
```

Order matters and there is no fallthrough. `tsconfig.json` mirrors it exactly, otherwise `tsc` and
Vite disagree about where a file lives.

| Import                   | Resolves to                         |
| ------------------------ | ----------------------------------- |
| `@/components/ui/button` | `resources/js/Components/ui/button` |
| `@/layouts/AppLayout`    | `resources/js/Layouts/AppLayout`    |
| `@/pages/Users/Index`    | `resources/js/Pages/Users/Index`    |
| `@/lib/table`            | `resources/js/lib/table`            |

The shadcn `components.json` alias is `@/components`; on Windows that resolves to the
capitalised `Components/` directory because `forceConsistentCasingInFileNames` is `false`. Write the
path as it exists on disk when you touch a file, and do not introduce a second `components/`
directory.

## 3. Layer import matrix

| Layer                       | May import                                                      | Must not import        |
| --------------------------- | --------------------------------------------------------------- | ---------------------- |
| `Components/ui/`            | `@base-ui/react/*`, `cn`, `@/lib/utils`, other `ui/` primitives | `Pages/`, `Modules/`   |
| `Components/` (app)         | `ui/`, other `Components/`, `@/lib`, `@/layouts`                | `Pages/`               |
| `Layouts/`                  | `Components/`, `ui/`, `@/lib`                                   | `Pages/`               |
| `Pages/`                    | everything                                                      | —                      |
| `Modules/{ctx}/hooks/`      | `@/lib`, its own `schemas`, `@/generated`                       | `Pages/`, Components   |
| `Modules/{ctx}/schemas/`    | Zod, generated types                                            | React, network, UI     |
| `Modules/{ctx}/components/` | `ui/`, `@/lib`, its own hooks/schemas                           | `Pages/`               |
| `lib/`                      | framework-agnostic helpers, generated types                     | `Pages/`, `Components` |

Pages are the top of the stack. Nothing imports them.

**Locality of behavior, and when it wins.** This matrix separates by _layer_, but separation has a
failure mode: what changes together should live together, and every row above is a decision that
splits something readable in one place.

> **Separate only along a boundary you can name. If you cannot name it, leave it local.**

Nameable here: a module's public surface, a transport (Inertia page vs TanStack Query hook), a UI
primitive (`Components/ui`), a network boundary (`Modules/{ctx}/hooks`). Unnameable: "it's a
component", "that's a store concern", "it could change independently". Concrete consequence: a page
that renders one thing and fetches it inline stays one file until a _second_ consumer appears — then
it becomes a hook (invariant 1), not before. The Rule of Three applies to React as it does to PHP:
two query hooks with the same shape and different endpoints are a coincidence, not an abstraction.

`PRINCIPLES/SKILL.md` §1 and §7.

## 4. ARCHITECTURAL INVARIANTS (verifiable)

The matrix above is prose until something fails. `tests/Architecture/FrontendBoundariesTest.php`
enforces invariants 1–4 and the table-kit clause of invariant 5 by grepping `.tsx` sources. The rest
of invariant 5 (a `.filter()` hidden in a page) is review-only. Every test skips when its tree is
empty, and the guard-rail test fails if the globs break — so a skipped suite never reads as green.

1. **Pages and Components MUST NOT call the network directly.** No `fetch(`, no `XMLHttpRequest`, no
   `WebSocket`, no Axios. The only place network I/O lives is `Modules/{ctx}/hooks/`. A page
   declares _what_ it shows; a hook knows _how_ to get it. Plain `fetch` in a hook MUST send the
   `X-XSRF-TOKEN` header from `lib/http.ts` for non-GET requests.
2. **Server state has one owner per screen.** Inertia page props own a page visit; TanStack Query
   owns a client-side panel that outlives the visit. Pages and components never import
   `@tanstack/react-query` or `@tanstack/react-table` directly — the hook owns the `queryOptions`
   factory, the key, the fetcher, the cache policy and the table instance. `app.tsx` is the single
   exception: it owns the `QueryClientProvider`. A screen must not fetch the same endpoint through
   both Inertia props and a query.
3. **Token discipline.** No hex, no `bg-[#…]`, no colour function inside an arbitrary value, and no
   raw Tailwind palette class (`text-red-600`, `bg-slate-900`, `border-white`). Use the `aq-*`
   scale, `surface-*` or `var(--token)`. The palette check exists because a literal palette class
   bypasses `globals.css` exactly like a hex literal does.
4. **Zero `any`.** No `any`, no `as any`, no `<any>`, no `@ts-ignore`, no `@ts-expect-error`. Use
   `unknown` plus a narrowing guard.
5. **Server data is rendered as received.** A component never calls `.sort()`, `.slice()`,
   `.filter()` or `.reverse()` on rows that came from a paginated server response. Pagination,
   sorting and filtering are URL/query-key state; the server is the only place they happen. A
   client-side re-sort breaks page 2 and makes the export disagree with the screen.

The same file also enforces three rules that are otherwise only aspirational: every `hover:` /
`focus-visible:` class list carries a `transition-*` (or `motion-reduce:`), every internal
`href="#anchor"` resolves to an `id` in the same tree, and no generated declaration file contains
`any` (the `.ts` scan skips `.d.ts`, so a backend `mixed` would otherwise ship an `any` with every
gate green). `tests/Architecture/FrontendRouteLinksTest.php` additionally resolves every literal
`href="/…"` in the React tree against the registered GET routes — a dead link is the one defect no
other check can see.

Two further rules are **review-only, and this file says so** rather than implying a machine checks
them: keyboard reachability and focus-ring visibility, and the `.dark`-class-only theming contract.
Neither is decidable from source.

## 5. `Components/ui/` — shadcn code ownership

Primitives are **copies in your repo**, not an npm component library. `npx shadcn@latest add
<name>` writes them and they are committed.

- **Base UI is the default (since July 2026).** Primitives import from `@base-ui/react/*`. No direct
  Radix primitive is installed; `cmdk` remains the documented transitive Radix exception for the
  command palette. Do not add new Radix primitives.
- The generated file is the place to customise. A call-site `className` that fights the primitive's
  own variants is the React equivalent of the old inline `pt` override — invisible to the next
  registry update and silently defeats the token layer.
- A local edit to a primitive gets a JSDoc comment at the top saying why upstream could not be used.
- No other UI library. If a primitive is missing, compose the existing ones or add it with the CLI.
- `components.json` style is `base-nova`; if the CLI asks, keep Base UI and keep the alias paths.
- Keep `lucide-react` as the only icon source.

## 6. Where state lives

| State                     | Home                                                                         | Never                                          |
| ------------------------- | ---------------------------------------------------------------------------- | ---------------------------------------------- |
| Server state (page visit) | Inertia props + `router.visit` / partial reloads                             | a query for the same endpoint                  |
| Server state (panel)      | TanStack Query `queryOptions` + `useQuery` / `useSuspenseQuery` in hooks     | mirrored into `useState`, context or a store   |
| Loading UI                | `useVisitPending()` for a visit; `<Suspense>` + `Skeleton` for a query panel | spinner-only full page, ad-hoc `animate-pulse` |
| Client state (UI)         | local `useState` / `useReducer` in the owning page                           | a global store added by reflex                 |
| Shared UI state           | a context in `Modules/{ctx}/` with a named boundary                          | context in `lib/` or inside `Pages/`           |
| Form draft                | `lib/forms.ts` (`useZodForm`) + `Components/Forms`                           | a second form state owner                      |
| Theme / sidebar           | `lib/theme.ts` (`useTheme`, `useSidebar`)                                    | anywhere else                                  |

No Redux, Zustand, Jotai or Pinia is installed. Adding one is an architecture change, not a
convenience: name the state, name the consumers, and document it here first.

Network hooks live in `Modules/{ctx}/hooks/`: one file per concern (`useEntities.ts` list,
`useEntity.ts` single, `useEntityMutations.ts` write). **Pages never call `fetch` and never import an
HTTP client** (invariant 1).

Cache keys follow `[entity, ...filters]` and the list key includes the serialised
`URLSearchParams`. Mutations invalidate the matching list and detail prefixes. The DataTable is
server-side (`manualPagination` / `manualSorting` / `manualFiltering`) — its events update the state
that produces the params, and the browser never sorts or paginates.

## 7. Inertia conventions

- Page folders `PascalCase`; page files `Index|Show|Create|Edit.tsx`.
- Every page renders `<AppLayout>` explicitly (the current repo convention) and a `<Head title>`.
  Inertia v3's `Page.layout` API exists but is not used here; switching is a repo-wide migration.
- Optimistic updates: `router.optimistic(...)` or `form.optimistic(...)` before the visit.
- The root blade is `resources/views/app.blade.php`. Fortify views point at Inertia pages via
  `Fortify::loginView(fn () => inertia('Auth/Login', [...]))` — there are no `auth/*.blade.php` files.
- Deferred props: `Inertia::defer(fn () => ...)` server-side, `<Deferred data="…">` client-side.
- SSR is disabled. Do not add a `bootstrap/ssr/` bundle.

## 8. Add a folder only with a reason

`Modules/{ctx}/schemas/` — required the moment the module has a mutating form.
`Modules/{ctx}/hooks/` — the only place network I/O is allowed (invariant 1).
`Modules/{ctx}/components/` — only when a component is shared by two or more pages of that module.
`Pages/{Ctx}/components/` — only for page-private components.
`lib/` — only for code with no React page or module ownership.

Document any new top-level folder in this file before merging it.
