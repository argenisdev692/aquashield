---
description: Generates the frontend for an existing backend module — Inertia pages, Colada queries, Zod schemas — from the module's generated TypeScript types. Run it only after the backend module exists and its types are generated.
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

1. `.opencode/skills/frontend/SKILL.md` — APIs, Colada, DataTable, Zod, a11y, tokens
2. `.opencode/skills/frontend/SKILL-ARCHITECTURE.md` — where each file goes, and the 4 invariants
3. `generated/generated.d.ts` — **this is the contract**; every prop type comes from it
4. `.opencode/skills/owasp/SKILL.md` §14, §15.5 — pagination, double validation, upload/signed-URL rules

## 3. Research the API, not the framework

**Context7 is authoritative for Tailwind, Zod, Pinia Colada and Inertia. It is NOT authoritative for
PrimeVue** — its indexed docs are v4, and the project is on v5 with deprecations and a mandatory
`license` option. For PrimeVue use `primevue.org/migration/v5` and the CHANGELOG only.

Check the installed version with `npm ls <pkg>` before writing an import. For Volt, run `ls src/volt`:
the CLI is alpha and the file names are not the PascalCase you typed.

## 4. Build in this order

1. `Modules/{ctx}/composables/useEntities.ts` — the **only** place network I/O lives (invariant 1)
2. `Modules/{ctx}/schemas/*.ts` — Zod schema + `z.infer<>`
3. `Pages/{Ctx}/Index.vue` — Colada query, `:lazy` DataTable, filters wired to the query key
4. `Show.vue` / `Create.vue` / `Edit.vue` — `defineOptions({ layout })`, `<Head>`, `@primevue/forms`
5. Page-private components in `Pages/{Ctx}/components/`

Pages never import an HTTP client. Forms never hand-roll validation in a submit handler.

## 5. Before merging

- [ ] Types imported from `generated/`, never re-declared
- [ ] Zero `any`, zero `as any`, zero `@ts-ignore`
- [ ] Vue 3.5 APIs: `useTemplateRef`, `useId`, `defineModel`, `onWatcherCleanup`, `defineSlots`
- [ ] Every query declares `staleTime` **and** `gcTime`; lists use `placeholderData: (prev) => prev`
- [ ] No hardcoded colour — tokens or PrimeUI bridge classes only, including any JS-side colour
- [ ] Row actions expose delete **and** restore; destructive action confirmed via `Dialog`
- [ ] Keyboard reachable, labelled, focus ring intact

```bash
npm run module:done
```

Report 5 lines: files created, the endpoints consumed, the schemas added, anything you could not
build without a backend change, and the gate result.
