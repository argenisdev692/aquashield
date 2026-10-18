---
description: Code review of the current changes against the project skills. Use for a review that must be grounded in this repo's rules rather than generic advice.
---

# CODE REVIEW

Generic review, grounded in this repo's rules. If a concern is not traceable to a skill, a test, or a
stated design decision, either justify it on its own merits or drop it.

## 1. Get the actual diff

```bash
git status && git diff && git diff --staged
```

Review the change, not the branch's history and not your memory of an earlier session.

## 2. Read the rules that the diff could violate

`.opencode/skills/backend-php/SKILL.md` · `SKILL-ARCHITECTURE.md` · `.opencode/skills/frontend/SKILL.md`
· `SKILL-ARCHITECTURE.md` · `.opencode/skills/owasp/SKILL.md` + `STACK.md`. Read only the sections the
diff touches.

## 3. Look for, in this order

1. **Security** — missing authorisation on a new route, an id that is not `whereUuid`'d, a response
   that serialises a raw model, a secret or PII reaching a log, a `v-html`, a client-only permission
   check, a missing 403 test on an ownership-scoped endpoint
2. **Correctness** — logic errors, unhandled edge cases, null/undefined paths, race conditions,
   transaction scope around an HTTP call or a dispatch
3. **Contracts** — an API contract broken by a Data class change; frontend types not regenerated after
   a DTO change; a cache key that no longer covers the filter it depends on
4. **Architecture** — a dependency pointing outward, a new layer with no test, an optional folder with
   no trigger, a layer declared in one mode and built in another
5. **Rule violations** — `any`, a hand-written type mirroring a `Data` class, a deprecated PrimeVue
   v4 API, a bare `with()`, a `JsonResource`, a hardcoded colour
6. **Dead weight** — a port with one implementation and no test, an adapter with no consumer, a
   `Contracts/` folder created "just in case"

## 4. Report

Ordered by severity, each finding with `file:line`, what breaks, and the smallest correct fix. State
what you checked and found clean — a review that only lists problems hides the parts you did not read.
Do not rewrite the diff; name the change.
