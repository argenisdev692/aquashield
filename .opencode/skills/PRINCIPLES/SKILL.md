---
name: principles
description: When to abstract, when to extract, and when duplication is correct. Covers the Rule of Three, knowledge-vs-code duplication (Hunt & Thomas, Henney), Fowler's cost of a presumptive feature, the OCP bet that FLAT-vs-PROGRESSIVE actually is, deep-vs-shallow modules (Ousterhout), SRP/LSP/ISP stated so SOLID is not just a logo, proportionality to artifact lifespan, and locality of behavior as the tie-breaker against over-separation.
---

# PRINCIPLES — when to abstract, when to duplicate

> The rules that constrain _your_ judgment calls, not the code you write. Load this before
> extracting something, creating an interface, or deciding a duplication is acceptable.
>
> Layout and layers: `backend-php/SKILL-ARCHITECTURE.md`. Framework rules: `backend-php/SKILL.md`.
> Security: `owasp/SKILL.md`.

Every rule here is stated with its **enforcement**, because a rule no gate executes and no reviewer
reads is a comment. Sources are primary; the year is when the idea was published, not when it was
found.

---

## 1. DRY — duplication of _knowledge_, not of code

Hunt & Thomas, _The Pragmatic Programmer_: _"every piece of **knowledge** must have a single,
unambiguous, authoritative representation."_ Kevlin Henney sharpens it: DRY is about duplication of
knowledge. Two classes that both compute a tax rate **from the same rule** must not exist twice. Two
classes that both call `->save()` and have no rule in common are not duplication of anything.

> **Rule of Three.** Two occurrences are a coincidence; the third is the pattern. Write the second
> copy when the second case is real, and extract on the third.

Extracting on the second is YAGNI wearing a DRY costume — you have paid construction cost for
knowledge you do not yet know you have, because a coincidence looked like knowledge. Not extracting
by the fifth is the technical debt Fowler _does_ want you to pay down.

| Situation                                                            | Verdict                                                             |
| -------------------------------------------------------------------- | ------------------------------------------------------------------- |
| Same business rule expressed twice                                   | **DRY fail.** One authoritative definition, everyone references it. |
| Same _shape_, no shared rule (two controllers each calling `save()`) | **Leave it.** Extracting is a shallow module.                       |
| You are about to write the second copy and feel the urge to extract  | **Stop.** Wait for the third.                                       |
| Three real cases exist and you still have three copies               | **DRY fail, now.**                                                  |
| Two cases exist, one of them is a _test_ of the other                | Not duplication. Do not merge them.                                 |

---

## 2. YAGNI — a presumptive feature has a price you can name

Origin: Kent Beck and Chet Hendrickson on the C3 project; Beck's answer to "the system will soon need
this" was _"you aren't going to need it"_. Martin Fowler turns it into economics in his
[Yagni bliki](https://martinfowler.com/bliki/Yagni.html): a presumptive feature — **code supporting a
feature that is not yet being made available for use** — costs

1. **cost of build** — the analysis, the code, the tests, and the review surface that follows it
2. **cost of delay** — the feature you could have shipped instead, two months earlier

Both are paid **even if the prediction was correct**, and the discount rate does not care whether you
were right. Jeremy Miller, same page: _"any extensibility point that's never used isn't just wasted
effort, it's likely to also get in your way as well."_

So a speculative abstraction is not free optimism. It is a bet with a stated cost, against a codebase
you will have to read, that you will get in your own way.

> **An `Interfaces/` folder, a base class for a subclass that does not exist yet, or an adapter with
> no consumer is not architecture. It is a presumptive feature with a maintenance cost attached.**

**Enforced:** `tests/Architecture/DesignSmellsTest.php` fails a module that declares a class nothing
else references, or that ships a `Contracts/`, `Interfaces/`, `Abstracts/`, `Base/`, `Concerns/` or
`Traits/` folder.

`src/Shared/` is deliberately **exempt**: it is a declared catalogue of where an adapter goes when its
need arrives, and asserting otherwise would be a test fighting its own policy. It is a promise, not
a layer — the first module that needs `StoragePort` is what makes it real.

### YAGNI is not an excuse for sloppy work

Kent Beck's _Tidy First?_ is explicit that "on tech debt with hackish solutions" and "wasting time
with YAGNI stuff" are two _different_ failures. Tidy and First are separate axes:

- **First (don't build it yet)** — the horizontal axis: how much do you build beyond the need.
- **Tidy (build it well)** — the vertical axis: how well you build what you do build.

YAGNI says do less. It never says do it badly. Code that is skipped should still be code you would
not be ashamed of, because "we will do it properly later" is a claim nobody has to keep.

---

## 3. OCP — PROGRESSIVE is a bet, and bets get a review date

Uncle Bob in _CleanCoders_ episode 10 tears down his own Open/Closed Principle: the promise "is in
fact a big lie... an illusion that depended upon prescience and perfect foresight." (Compare the 2000
paper, where OCP is stated as the design heuristic to place behaviour behind walls.)

That is not an argument against layers. It is an argument against **unpriced** layers.

`SKILL-ARCHITECTURE.md` §4 already does the thing almost nobody does: it defines the escalation
triggers _and the de-escalation rule_ — 0/4 triggers for two consecutive quarters demotes
PROGRESSIVE back to FLAT. Read that as what it is: **the discipline that makes an OCP bet safe.** A
bet with no exit is a trap; a bet with a scheduled review is a bet.

> Declaring `mode: PROGRESSIVE` is asserting: this domain will have invariants, and the cost of being
> wrong (build + delay, paid even when right) is worth it. That is a claim, so it gets the same
> scrutiny as any other claim — with the date the review is due.

OCP is the only SOLID principle that costs you up front. The other four are close to free. When a
change is expensive and you cannot prove it is needed yet, that is the argument for FLAT, not a
detail.

---

## 4. Deep modules — the measurable version of the no-lock rule

`SKILL-ARCHITECTURE.md` asks: _"would deleting a layer fail a test?"_ That is a **test** for whether a
boundary is real. John Ousterhout, _A Philosophy of Software Design_ (2018), asks a different and
complementary question: **is the interface much simpler than the implementation?**

- **Deep** — simple interface, rich implementation. It hides complexity. Unix file I/O: five calls,
  enormous machinery behind them.
- **Shallow** — interface nearly as complex as the implementation. It _exposes_ complexity and adds
  its own. Ousterhout's example: a `addNullValueForAttribute($a)` that only calls `data.put($a, null)`.

Consequences for this repo:

- Small is not the goal. **A big module with a small interface beats three small modules with three
  interfaces.** The "split it up so it reads better" instinct produces shallow modules — the failure
  Clean Code is criticised for.
- A wrapper that forwards its caller's arguments unchanged adds a name and nothing else.
- **A wrapper that transforms an argument is not dumb** — it is doing work. `R2StorageAdapter::delete()`
  calling `$this->storage->delete($this->normalizeObjectKey($path))` earns its place.
- A **port** with one implementation and no test that substitutes it is shallow by Ousterhout's
  definition, which is exactly the Repository Optionality Rule.

**Enforced:** `tests/Architecture/DesignSmellsTest.php` fails any method whose entire body is
`return $other->method($itsOwnArgs);` — narrowly, so a method that transforms an argument is not
flagged. This check found four candidates in this repo on its first calibration and, once narrowed,
zero: the four were all doing real work.

---

## 5. The five SOLID principles, named

The acronym is a mnemonic coined by **Michael Feathers** around 2004; the principles are Uncle Bob's
(2000 paper _Design Principles and Design Patterns_). Naming SOLID is not applying it.

|       | Principle             | As it applies here                                                                                                                                                                                                                                                         | Enforcement |
| ----- | --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------- |
| **S** | Single Responsibility | Not "one reason to change" as a slogan — group what changes for the **same reason**, split what changes for different reasons. A model that validates, renders and exports has three reasons.                                                                              | review      |
| **O** | Open/Closed           | §3 above. Priced, with a review date.                                                                                                                                                                                                                                      | review      |
| **L** | Liskov Substitution   | A subtype must be usable anywhere its parent is, without the caller changing. If `EloquentSlotRepository` forces a caller to `->toModel()` afterwards, it is not a `SlotRepository`. Two implementations with different contracts is a renamed mismatch, not polymorphism. | review      |
| **I** | Interface Segregation | A client must not depend on methods it does not call. One fat `StoragePort` used by three callers is a SRP failure wearing a port. Split when callers genuinely differ, not to look tidy.                                                                                  | review      |
| **D** | Dependency Inversion  | High-level policy must not import low-level detail; both depend on abstractions. That is the boundary matrix in `SKILL-ARCHITECTURE.md` §2.                                                                                                                                | `arch()`    |

**The DIP here is not free.** A port with a single implementation and no test that substitutes it
buys a file and a `use` statement, and costs a reader two hops to understand one query. That is the
optionality rule, and it is the honest form of DIP — abstraction where it pays, indirection nowhere
else.

---

## 6. Proportionality — the principles scale with the lifespan of the code

A rule that is right for a module that lives five years is not right for a command that runs once.

> **Declare the lifespan before you build.** `lifespan: one-shot | module` in the spec, and hold the
> ceremony to it.

| Lifespan                                                         | What it gets                                                                                                | What it does not get                                                          |
| ---------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| `one-shot` — a migration script, a seeder, a maintenance command | strict types, a test, clear naming, Pint                                                                    | `Data` classes, filter DTOs, exports, policies, 403 tests, a service provider |
| `module` — the default                                           | the whole FLAT contract: `Data/`, `FilterData`, ownership scope, 401/403/422, bulk pair, exports on request | `Domain/`, `Application/`, `Infrastructure/` until §4 triggers fire           |

The failure this prevents is specific: an agent asked for a one-line Artisan command produces a
Data class, a repository, a policy and a 403 test suite. Every one of them is individually
defensible, and collectively it is the "just in case" folder the YAGNI rule forbids.

---

## 7. Locality of behavior — the tie-breaker against over-separation

Michael Feathers' **locality of behavior**: _gather together the things that change together._
Everything in this repo separates concerns into layers, and separation is right — but it has a
failure mode, and it is the one SOLID itself is sometimes blamed for.

The rule that resolves it:

> **Separate only along a boundary you can name. If you cannot name it, leave it local.**

Nameable boundaries: a persistence format, an external API, a module's public surface, a transport.
Unnameable: "it's cleaner over there", "that's not this layer's job", "it might change
independently". Those three are how a four-file change happens to move one line.

This matters more with agents than with humans. An agent that splits a coherent unit across nine
files loses the ability to see it at all — every hop is context it has to reload, and the piece it
never reads is the piece it gets wrong. Locality is a correctness tool for agent-written code, not
just a style preference.

The tension between §1 (extract at the third occurrence) and this section is real and resolves like
this: **extraction needs a name and a reason; a file move needs a name.** Splitting a function
across modules because it got long is not extraction, it is guessing.

---

## 8. The duplication you are allowed to keep

Naming the exceptions is what stops an absolute rule from being applied where it hurts.

| Accept                                                                                 | Why                                                                                                        |
| -------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Two identical controller actions in two modules                                        | They will diverge — different filters, different policies. Merging them is a premature shared abstraction. |
| A test that repeats a setup the production test already has                            | Setup is the specification of the case. A shared fixture hides the case.                                   |
| Two Colada composables with the same shape, different endpoints                        | The shape is a coincidence; the endpoints are the knowledge.                                               |
| A `pt` override at a call site when a Volt primitive is being _temporarily_ overridden | Deliberate, with a comment naming when it goes. Not a pattern to repeat.                                   |
| Test code that mirrors production structure                                            | Independence is the point. A test sharing production's shape cannot catch a structural error.              |

What is **not** acceptable, ever: a second place where a business rule, a permission name, an owner
scope or a filter is defined. Those are knowledge, and knowledge has one authoritative home.

---

## 9. The agent-specific version

Measured failure mode of coding agents, 2026: they **over-engineer and emit more code than is
necessary** (Scott Logic, on the "Ponytail" skill, June 2026), and they **violate DRY because they do
not check what already exists** (Faros, 2026) — an agent produces its result whether or not the
correct abstraction is already in the tree.

Both antidotes are already in this repo. What was missing is the diagnosis, so:

- The optionality table (`SKILL-ARCHITECTURE.md` §5) is not bureaucracy. It is the countermeasure to
  a default that over-builds.
- "Check the graph before introducing anything new" (`rules.md`) is not a style preference. It is the
  countermeasure to duplicating what exists.
- Skipping a layer is a **decision with a reason**, not an omission. If the agent did not build
  `Domain/`, that is compliance, not a shortcut.

---

## 10. Before you abstract — six questions

1. **What is the knowledge?** Name the rule this abstraction carries. If you cannot name it in one
   sentence, there is no knowledge to share and the abstraction is a rename.
2. **How many real cases exist?** Two is a coincidence. Three is a pattern.
3. **What does the interface cost?** Count public members. If it is not much smaller than the
   implementation, it is shallow.
4. **Which way does the dependency point?** If it points outward, you are about to invert something
   that was fine.
5. **What breaks if this is wrong?** An OCP bet costs build + delay. A wrong helper costs a rename.
   Match the commitment to the reversibility.
6. **Can you name the boundary?** If not, keep it local and move on.
