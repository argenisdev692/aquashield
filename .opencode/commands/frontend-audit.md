---
description: Audits frontend code against the token, Colada, Volt, PrimeVue 5, TypeScript, accessibility and security rules. Produces a PASS/FAIL/WARN checklist with file:line, fixes what is unambiguous, re-verifies. The rules live in the skills — this command is only the report format and the loop.
---

# FRONTEND AUDIT

## 1. Read the rules first

| Scope                                   | Read                                                     |
| --------------------------------------- | -------------------------------------------------------- |
| APIs, Colada, DataTable, tokens, a11y   | `.opencode/skills/frontend/SKILL.md`                     |
| File placement + the 4 layer invariants | `.opencode/skills/frontend/SKILL-ARCHITECTURE.md` §3, §4 |
| Security                                | `.opencode/skills/owasp/SKILL.md` §12, §14, §15.5        |

The checklist is generated from the skills, not from memory. If a rule is not in a skill, it is not a
checklist item — unless you are proposing a new rule, marked `PROPOSED` separately.

## 2. Establish the truth of the repo first

```bash
npm ls vue primevue @primevue/forms @pinia/colada    # installed, not declared
ls src/volt                                         # the real Volt catalogue
```

`resources/js/Pages/` currently holds only `Auth/*` and `Welcome`, and there is no module frontend
yet. **An audit with no module in scope is a shell audit** — say so rather than reporting a vacuous
pass.

## 3. Produce the checklist

Per applicable rule: `PASS` · `FAIL` (`file:line`, one sentence) · `WARN` (state the judgement) ·
`N/A` (state why). Always include the four layer invariants from `SKILL-ARCHITECTURE.md` §4 with
their enforcement status.

The failures that matter most on this stack:

- **`any` / `as any` / `@ts-ignore` anywhere** — hard fail, no exceptions
- a hand-written interface mirroring a backend `Data` class — hard fail, it is the source of the `any`s
- a PrimeVue **v4** API: `Button label|icon|badge|loading` props, `InputMask` as a component,
  `AutoComplete multiple`, `Password`, `ScrollPanel` — these are deprecated in v5 and removed in v6
- missing `staleTime` or `gcTime` on a query; a paginated list without `placeholderData`
- a hardcoded colour (hex, `bg-red-600`, `bg-[#…]`) in markup, in `pt`, or in JS
- a DataTable sorting, filtering or paginating in the browser instead of `:lazy`
- `v-html` on user input; a secret or raw error text reaching the client
- a client-side-only authorisation check (the server must re-check)
- only delete, without restore, on a row-selectable screen

## 4. Fix only what is unambiguous

Auto-fix: a missing `staleTime`/`gcTime`, a `v-model` without `defineModel`, a missing `<label for>`,
a hardcoded colour replaced by its token, a deprecated PrimeVue prop rewritten as a slot child.

Never auto-fix: a component split, a store extracted, a schema changed, an API call added or moved.
Propose those with a diff.

## 5. Re-verify and report

```bash
npm run module:done
```

Report: score, the `FAIL` list with `file:line`, what you fixed, what you propose and did not do, and
the real gate output. Note explicitly if `resources/js/generated/generated.d.ts` is still a stub — the
whole generated-types rule is unverifiable until the first module exists.
