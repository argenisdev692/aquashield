---
description: Generates the frontend for an existing backend module — Inertia pages, TanStack Query hooks, TanStack Table v9, Zod schemas — from the module's generated TypeScript types. Run it only after the backend module exists and its types are generated.
---

# FRONTEND NEW — screen set for a module

## 1. Preconditions — verify, do not assume

```bash
ls resources/js/generated/generated.d.ts    # must exist and not be only a header
ls src/Modules/{Module}                     # the backend module must be there
```

Types are generated from the module's Spatie `Data` classes. If the file is a stub, stop and run
`npm run module:codegen` on the backend first. Writing the types by hand is a hard fail — it is how
`any` and silent drift get in.

## 2. Read, in this order

1. `.opencode/skills/frontend/SKILL.md` — React 19 APIs, Inertia v3, TanStack, shadcn, Zod, a11y, tokens
2. `.opencode/skills/frontend/SKILL-ARCHITECTURE.md` — where each file goes, and the 5 invariants
3. `resources/js/generated/generated.d.ts` — **this is the contract**; every prop type comes from it
4. `.opencode/skills/owasp/SKILL.md` §14, §15.5 — pagination, double validation, upload/signed-URL rules

## 3. Research the API, not the framework

**Context7 is authoritative for React 19, Inertia v3 React, TanStack Query v5, TanStack Table v9,
shadcn/Base UI, Tailwind and Zod** — scoped to the installed major. Two traps:

- **TanStack Table v9** is not v8: it is `useTable` + a required `features` object
  (`tableFeatures`/`stockFeatures`), row models on that object, `sortFn` not `sortingFn`, and
  `table.state` not `table.getState()`. v8 snippets from a blog post will not compile.
- **shadcn/ui defaults to Base UI** (since July 2026). Composition is the `render` prop, not
  Radix's `asChild`. Radix is not installed; do not add it.

Check installed versions with `npm ls <pkg>` before writing an import. The UI catalogue is
`ls resources/js/Components/ui`; components are source you own, not an npm library.

## 4. Build in this order

1. `Modules/{ctx}/hooks/` — **only if the screen has a client-owned async panel** (polling, live
   inbox, dashboard widget). It is the only place network I/O lives (invariant 1): a
   `queryOptions()` factory + `useQuery`/`useSuspenseQuery` + `staleTime`/`gcTime`;
   `X-XSRF-TOKEN` from `lib/http.ts` for writes. A normal page visit uses Inertia props instead.
2. `Modules/{ctx}/schemas/*.ts` — Zod schema + `z.infer<>`
3. `Pages/{Ctx}/Index.tsx` — Inertia props + `router.visit` (or `useVisitPending()` for the
   table's loading state), server-side table (`manualPagination` / `manualSorting` /
   `manualFiltering`), filters wired to the query string, export built from `lib/table.ts`
4. `Show.tsx` / `Create.tsx` / `Edit.tsx` — `<AppLayout>`, `<Head>`, `useZodForm` + the Forms kit,
   Base UI `Dialog` for destructive confirmation; each waiting panel gets `<Suspense>` +
   `Skeleton` (or `Deferred` for an Inertia deferred prop)
5. Page-private components in `Pages/{Ctx}/components/`

Pages never import an HTTP client and never import `@tanstack/*` directly. Forms never hand-roll
validation in a submit handler.

## 5. Before merging

- [ ] Types imported from `generated/`, never re-declared
- [ ] Zero `any`, zero `as any`, zero `@ts-ignore`
- [ ] React 19 APIs used correctly: `useId`, `ref` as prop, `useEffectEvent`, `<Activity>` where it fits
- [ ] React Compiler is not fought: no reflexive `useMemo` / `useCallback`, no mutation after render
- [ ] Every query declares `staleTime` **and** `gcTime` through a `queryOptions()` factory; lists
      use `placeholderData: keepPreviousData`
- [ ] Every waiting panel has `<Suspense>` + a `Skeleton` that matches its final layout
- [ ] Table is server-side; the browser does not sort, slice, filter or paginate
- [ ] No hardcoded colour and no raw Tailwind palette class, including any JS-side colour
- [ ] Row actions expose delete **and** restore; destructive action confirmed via `Dialog`
- [ ] Keyboard reachable, labelled, focus ring intact
- [ ] `npm run module:done`

Report 5 lines: files created, the endpoints consumed, the schemas added, anything you could not
build without a backend change, and the gate result.
