# {Module} — ARCHITECTURE.md (FLAT variant, the default)

> Copy this file to `src/Modules/{Module}/ARCHITECTURE.md` and fill in the
> brackets. Full rules → `.opencode/skills/backend-php/SKILL-ARCHITECTURE.md`.
> The test `tests/Architecture/ModuleComplexityTest.php` parses the `mode:` line.

mode: FLAT

## Why FLAT

[1-2 sentences: e.g. "5-field CRUD, no invariants beyond validation,
no second consuming module".]

## Triggers evaluated (0-1 of 4 to stay FLAT)

- [ ] ≥3 models with business rules
- [ ] Invariants (state machine, lifecycle, precondition)
- [ ] Tests that need to swap out persistence or an external service
- [ ] Roadmap: extraction to a microservice

## Accepted debt

[Nothing / what is deliberately postponed and its promotion condition.]

## Notes

- No `Domain/`, `Application/`, `Infrastructure/` folders (the test fails if they exist).
- `Data/` is mandatory: it is what `typescript:transform` and Scramble read. Omitting it breaks both silently.
- No `Contracts/`, `Interfaces/`, `Abstracts/`, `Base/`, `Concerns/` or `Traits/` folder — the architecture
  test fails all of them. A port belongs in a layer folder, named for the thing it abstracts.
- Handlers query the Eloquent model directly (Repository Optionality
  Rule: no port with a single implementation).
- **Every class here has a consumer.** A class nothing references is a presumptive feature (Fowler):
  it cost construction and delay, and it will get in the way.
- **Ownership is not optional for a user-owned aggregate.** `permission:*` authorises the
  action, never the row, and a uuid is unguessable rather than secret (OWASP API1:2023).
  The generated module ships `scopeOwnedBy()` + `assertOwned()` and scopes `index` and the
  export. A hand-written module owes the same, with a 403-for-non-owner test.
- **A method that only forwards its own arguments is a failing test.** If you wrote a wrapper, make
  it do something or delete it (`PRINCIPLES/SKILL.md` §4).
- **Two copies of a rule is a DRY fail; two copies of a shape is not.** Rule of Three: extract on the
  third, not the second.
- Responses: Spatie `Data` (`{Entity}Data::from()`), never `JsonResource`
  (exception: public third-party API with `JsonApiResource`, see skill §5).
