---
name: frontend-architecture
description: Directory tree, layer boundaries and file placement for the Vue 3 + Inertia frontend. Covers the @ alias resolution, the Volt primitives location in src/volt, the layer import matrix with its verifiable invariants, Pinia store placement, and where server state lives.
---

# FRONTEND ARCHITECTURE — directory tree and layers

> Rules, patterns and APIs → `frontend/SKILL.md`.
> Security → `owasp/SKILL.md`.

## 1. The tree as it actually is

```
src/
├── volt/                    51 Volt primitives + utils.ts   ← CODE-OWNERSHIP, committed
├── Modules/                 empty — one folder per domain when the backend module lands
└── Shared/                  PHP only: ports, adapters, DTOs (see backend-php/SKILL-ARCHITECTURE.md §5)

resources/
├── css/
│   ├── app.css              Tailwind v4 entry — import order and @source are load-bearing
│   └── globals.css          ALL design tokens + the PrimeUI variable bridge
├── js/
│   ├── app.ts               createInertiaApp — Pinia, Colada, PrimeVue, services
│   ├── Components/
│   │   ├── DataTable/       server-first table kit + CrudDialog
│   │   └── Forms/           useZodForm field kit, buttons, dropzones (README.md)
│   ├── Layouts/             AppLayout + AuthLayout (present)
│   ├── lib/
│   │   ├── utils.ts         cn() — the class-merge helper
│   │   ├── forms.ts         useZodForm — Inertia + Zod, error merge + focus
│   │   ├── files.ts         file validation and byte formatting
│   │   ├── phone.ts         libphonenumber-js formatting + phoneSchema
│   │   ├── table.ts         one query contract for table + export
│   │   └── theme.ts         the single useColorMode store
│   ├── Pages/               Inertia pages — the ONLY layer using usePage()/useForm()
│   │   ├── Auth/            Login, Register, ForgotPassword, ResetPassword,
│   │   │                    VerifyEmail, ConfirmPassword, TwoFactorChallenge
│   │   ├── Dashboard/
│   │   └── {Context}/       Index, Show, Create, Edit (+ page-private components/)
│   └── generated/           generated.d.ts + transformer manifest from `typescript:transform`
└── views/
    ├── app.blade.php        Inertia root — @inertia, @inertiaHead, @vite
    └── errors/
```

**There is no `resources/js/types/`.** The type surface is `resources/js/generated/generated.d.ts`
plus ambient `*.d.ts` files placed next to the code that needs them. Do not document or create a
`types/` folder by reflex — if a shared type appears twice, that is a `Data` class missing from
`typescript:transform`, not a folder to create.

## 2. The `@` alias — and why it is not one rule

Vite aliases use **first string-prefix match with no filesystem check**, and a `find` ending in `/`
never matches (the resolver appends the slash itself). So the mapping is split by prefix:

```js
// vite.config.js
resolve: {
    alias: [
        { find: '@/volt',    replacement: resolve('./src/volt/') },
        { find: '@/pages',   replacement: resolve('./resources/js/Pages/') },
        { find: '@/layouts', replacement: resolve('./resources/js/Layouts/') },
        { find: '@',         replacement: resolve('./resources/js/') },
    ],
},
```

Order matters and there is no fallthrough. `tsconfig.json` must mirror it exactly, otherwise
`vue-tsc` and Vite disagree about where a file lives.

| Import                     | Resolves to                           |
| -------------------------- | ------------------------------------- |
| `@/volt/Button.vue`        | `src/volt/Button.vue`                 |
| `@/layouts/AuthLayout.vue` | `resources/js/Layouts/AuthLayout.vue` |
| `@/Components/Foo.vue`     | `resources/js/Components/Foo.vue`     |

## 3. Layer import matrix

| Layer                    | May import                                           | Must not import    |
| ------------------------ | ---------------------------------------------------- | ------------------ |
| `src/volt/`              | `primevue/*`, `@primevue/*`, `primeicons`, `./utils` | anything app-level |
| `Components/`            | `Layouts/`, `Components/`, `@/volt/*`, `@/lib`       | `Pages/`           |
| `Layouts/`               | `Components/`, `@/volt/*`, `@/lib`                   | `Pages/`           |
| `Pages/`                 | everything                                           | —                  |
| `src/Modules/` (per ctx) | `@/volt/*`, `@/lib`, its own `stores                 | composables        | schemas` | `Pages/` |

Pages are the top of the stack. Nothing imports them.

**Locality of behavior, and when it wins.** This matrix separates by _layer_, but separation has a
failure mode: Michael Feathers' counter-argument is that what changes together should live together,
and every row above is a decision that splits something that was readable in one place.

> **Separate only along a boundary you can name. If you cannot name it, leave it local.**

Nameable here: a module's public surface, a transport (Inertia page vs Colada query), a UI primitive
(`src/volt`), a network boundary (composables). Unnameable: "it's a component", "that's a store
concern", "it could change independently". Concrete consequence: a page that renders one thing and
fetches it inline stays one file until a _second_ consumer appears — then it becomes a composable
(invariant 1), not before. And the Rule of Three applies to Vue just as it does to PHP: two Colada
composables with the same shape and different endpoints are a coincidence, not an abstraction.

`PRINCIPLES/SKILL.md` §1 and §7.

## 4. ARCHITECTURAL INVARIANTS (verifiable)

The matrix above is prose until something fails. These four are enforceable today, and
`tests/Architecture/FrontendBoundariesTest.php` enforces them.

1. **Pages and Components MUST NOT call the network directly.** No `fetch(`, no `XMLHttpRequest`, no
   axios import. The only place network I/O lives is `src/Modules/{ctx}/composables/`. A page declares
   _what_ it shows; a composable knows _how_ to get it.
2. **`.vue` files outside `src/volt/` MUST NOT import `primevue/*` or `@primevue/*` directly.** Every UI
   primitive is reached through `@/volt/*`. (`resources/js/app.ts` is the single exception — it
   registers `PrimeVue`, `ToastService`, `ConfirmationService` and the `Tooltip` directive.)
3. **`src/volt/**` MUST NOT import from `resources/js`** in any form. The primitives are owned by this
   repo but they belong to the layer _below_ the app; an import of `@/…` there is a captured
   dependency that the next `volt-vue add` will not reproduce.
4. **No call-site `pt` override outside `src/volt/`.** Customise a Volt primitive by editing its own
   `pt` pass-through config, not by passing `:pt="{…}"` at the usage site. Inline `pt` is invisible to
   a Volt upgrade and silently defeats the token layer.

Invariants 1-4 are enforced by `tests/Architecture/FrontendBoundariesTest.php`, which greps the
`.vue` sources and skips itself when a tree is empty. The same file also greps the two rules that are
otherwise only aspirational: no hardcoded colour, and no `any` / `as any` / `@ts-ignore`.

Invariant 2 has a declared carve-out: PrimeVue **services, composables, the config object, the
Tooltip directive, `Column` and the icons subpath** have no Volt wrapper and stay importable directly.
A PrimeVue _component_ always goes through `@/volt/*`. Anything not in that list is a component.

Two further frontend rules are **review-only, and this file says so** rather than implying a machine
is checking them: keyboard reachability and focus-ring visibility, and the `.dark`-class-only theming
contract. Neither is decidable from source.

## 5. `src/volt/` — code ownership

Volt components are **copies in your repo**, not an npm library. `npx volt-vue add <Name>` downloads
them and they are committed. The CLI is an alpha; expect drift.

- **The CLI is PascalCase; the file it writes is not always.** `npx volt-vue add DataTable` produces
  `Datatable.vue`, `InputText` → `Inputtext.vue`, `DatePicker` → `Datepicker.vue`. On Linux the import
  must match the file on disk exactly. Check `ls src/volt` before writing an import.
- **`Column` is not a Volt component.** It comes straight from PrimeVue: `import Column from 'primevue/column'`.
  Also not in the CLI: `AutoComplete`, `Tooltip`, `ScrollPanel`, `InputGroup`, `IconField`.
- **`Tooltip` is a directive**, registered in `app.ts`, not a component.
- **Customise through the component's own `pt` pass-through config**, never at the call site
  (invariant 4).
- A local edit to a generated file gets a JSDoc comment at the top saying why upstream could not be used.
- No other UI library. If a primitive is missing, compose existing Volt components.
- `src/volt/` contains a few components PrimeVue 5 has already deprecated (`InputMask`, `Password`,
  `MultiSelect`). Do not build new screens on them — see `frontend/SKILL.md` §6.

## 6. Where state lives

| State             | Home                                               | Never                                            |
| ----------------- | -------------------------------------------------- | ------------------------------------------------ |
| Server state      | Pinia Colada `useQuery` / `useMutation`            | mirrored into a Pinia store                      |
| Client state (UI) | Pinia setup store in `Modules/{ctx}/stores/`       | inside `Pages/`, `common/`, or an inline factory |
| Form draft        | `lib/forms.ts` (`useZodForm`) + `Components/Forms` | a Pinia store, a second form state owner         |
| Theme / sidebar   | `lib/theme.ts` (`useColorMode`)                    | anywhere else                                    |

Network composables live in `Modules/{ctx}/composables/`: one file per concern
(`useEntities.ts` list, `useEntity.ts` single, `useEntityMutations.ts` write). **Pages never call
`fetch` and never import an HTTP client** (invariant 1).

Cache keys are functions and follow `[entity, ...filters]`. Mutations invalidate the matching list and
detail keys. The DataTable runs server-side `:lazy` — its events update the reactive filters object the
query key depends on; the browser never sorts or paginates.

## 7. Inertia conventions

- Page folders `PascalCase`; page files `Index|Show|Create|Edit.vue`.
- Layout via `defineOptions({ layout })` per page — never wrap each page by hand.
- `<Head>` for the title on every page.
- Optimistic updates: `router.optimistic(...)` then the post.
- The root blade is `resources/views/app.blade.php`. Fortify views point at Inertia pages via
  `Fortify::loginView(fn () => inertia('Auth/Login', [...]))` — there are no `auth/*.blade.php` files.

## 8. Add a folder only with a reason

`Modules/{ctx}/schemas/` — required the moment the module has a mutating form.
`Modules/{ctx}/stores/` — only when client state is genuinely shared.
`Modules/{ctx}/composables/` — the only place network I/O is allowed (invariant 1).
`Pages/{Ctx}/components/` — only for page-private components.

Document any new top-level folder in this file before merging it.
