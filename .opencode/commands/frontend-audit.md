---
description: Audits frontend code against the token, TanStack Query/Table, shadcn/Base UI, TypeScript, accessibility and security rules. Produces a PASS/FAIL/WARN checklist with file:line, fixes what is unambiguous, re-verifies. The rules live in the skills — this command is only the report format and the loop.
---

# FRONTEND AUDIT

## 1. Read the rules first

| Scope                                   | Read                                                     |
| --------------------------------------- | -------------------------------------------------------- |
| APIs, TanStack, table, tokens, a11y     | `.opencode/skills/frontend/SKILL.md`                     |
| File placement + the 5 layer invariants | `.opencode/skills/frontend/SKILL-ARCHITECTURE.md` §3, §4 |
| Security                                | `.opencode/skills/owasp/SKILL.md` §12, §14, §15.5        |

The checklist is generated from the skills, not from memory. If a rule is not in a skill, it is not a
checklist item — unless you are proposing a new rule, marked `PROPOSED` separately.

## 2. Establish the truth of the repo first

```bash
npm ls react @inertiajs/react @tanstack/react-query @tanstack/react-table @base-ui/react shadcn
ls resources/js/Components/ui        # the primitives that actually exist
```

`resources/js/Pages/` holds the Auth set, Dashboard, Welcome, Users, Backups, Settings, CompanyData
and ActivityLog. **An audit with no module in scope is a shell audit** — say so rather than
reporting a vacuous pass.

## 3. Produce the checklist

Per applicable rule: `PASS` · `FAIL` (`file:line`, one sentence) · `WARN` (state the judgement) ·
`N/A` (state why). Always include the five layer invariants from `SKILL-ARCHITECTURE.md` §4 with
their enforcement status.

The failures that matter most on this stack:

- **`any` / `as any` / `@ts-ignore` anywhere** — hard fail, no exceptions
- a hand-written interface mirroring a backend `Data` class — hard fail, it is the source of the `any`s
- a **TanStack Table v8** API in the v9 codebase: `useReactTable`, `get*RowModel()` options,
  `sortingFn`, `table.getState()` — v9 requires `useTable` + `features` + `table.state`
- a table sorting, filtering or paginating client-side when the endpoint is paginated
- missing `staleTime` or `gcTime` on a query; a paginated list without `placeholderData`
- a query defined inline instead of a `queryOptions()` factory, or a suspense panel with no
  `<Suspense>`/`Skeleton` fallback
- a page that receives a list as an Inertia prop **and** queries the same endpoint with TanStack Query
- TanStack Query imported by a page or component instead of `Modules/{ctx}/hooks/`
- a hardcoded colour (hex, `bg-red-600`, `bg-[#…]`) in markup, a variant, or JS
- `dangerouslySetInnerHTML` on user input; a secret or raw error text reaching the client
- a Radix import or a second UI library; a call-site `className` war against a shadcn primitive
- a client-side-only authorisation check (the server must re-check)
- only delete, without restore, on a row-selectable screen
- a `hover:` / `focus-visible:` state with no `transition-*`

## 4. Fix only what is unambiguous

Auto-fix: a missing `staleTime`/`gcTime`, a missing `<label for>` or `aria-describedby`, a
hardcoded colour replaced by its token, a `radix-ui` import replaced by the installed Base UI
primitive, a `window.confirm()` replaced by the kit's `Dialog`.

Never auto-fix: a component split, a table rewritten to TanStack Table, a schema changed, an API call
added or moved, a `useMemo` deleted purely on compiler theory. Propose those with a diff.

## 5. Re-verify and report

```bash
npm run module:done
```

Report: score, the `FAIL` list with `file:line`, what you fixed, what you propose and did not do, and
the real gate output. Note explicitly if `resources/js/generated/generated.d.ts` is still a stub — the
whole generated-types rule is unverifiable until the first module exists.
