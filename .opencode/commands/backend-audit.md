---
description: Audits a module or the whole app against the architecture, security, audit-trail and test rules. Produces a PASS/FAIL/WARN checklist with file:line, fixes what is safely fixable, then re-verifies. The rules live in the skills — this command is only the report format and the loop.
---

# BACKEND AUDIT

## 1. Read the rules first — the checklist is generated from them, not remembered

| Scope                        | Read                                                                    |
| ---------------------------- | ----------------------------------------------------------------------- |
| Language / framework         | `.opencode/skills/backend-php/SKILL.md`                                 |
| Layers, invariants, triggers | `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md` §2, §3, §5, §6     |
| Security                     | `.opencode/skills/owasp/SKILL.md` (all 17 items) + `owasp/STACK.md` §17 |

Do not paste a checklist from memory. If a rule exists in a skill, it is a checklist item; if it does
not, it is not — unless you are proposing a new rule, in which case mark it `PROPOSED` separately.

## 2. Establish the truth of the repo first

```bash
composer show laravel/framework spatie/laravel-data   # installed, not declared
```

`src/Modules/` is empty today, so an audit with no module in scope means the `app/` + `src/Shared/`
shell only. **Say that in the report** rather than reporting a vacuous pass.

## 3. Produce the checklist

For every applicable rule: `PASS` · `FAIL` (with `file:line` and one sentence) · `WARN` (judgement
call, state it) · `N/A` (state why). Include the invariant registry rows from
`SKILL-ARCHITECTURE.md` §3 with their declared enforcement — a row marked `review` that nobody
reviewed is a `FAIL`, not a `PASS`.

Weights that matter most here:

- **Security items are never `WARN`.** Missing authorisation, missing 403 coverage, a `JsonResource`
  bypassing the generated types, secrets in logs — these are `FAIL` or the audit did not happen.
- **An architecture invariant with no enforcing test** is a `WARN` at best, per the no-lock rule.
- **Doc-vs-code drift counts.** If a skill claims something the generator or the code does not do,
  that is a `FAIL` against the skill, and the skill is what needs fixing.

## 4. Fix only what is unambiguous

Auto-fix: wrong namespaces, missing `declare(strict_types=1)`, a `with()` missing explicit columns, a
missing `whereUuid`, a deprecated API, a formatting issue.

Never auto-fix: a mode change, a layer added or removed, a test deleted or weakened, an authorisation
decision. Those are decisions — propose them with a diff and let the human choose.

## 5. Re-verify and report

```bash
npm run module:done     # or module:codegen if a Data/enum/model/controller changed
```

Report: score, the `FAIL` list with `file:line`, what you fixed, what you propose and did not do, and
the real gate output. If any step failed, say so with the output — never report a module clean on a
partial run.
