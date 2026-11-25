---
description: Audits a module or the whole app against the architecture, security, audit-trail and test rules. Produces a PASS/FAIL/WARN checklist with file:line, fixes what is safely fixable, then re-verifies. The rules live in the skills — this command is only the report format and the loop.
---

# MANDATORY CODEBASE MEMORY WORKFLOW

**Primary tool: `codebase-memory-mcp`.**

Use the indexed knowledge graph as the primary code-discovery mechanism. Minimize unnecessary file reads, grep operations, repeated queries, and context consumption without sacrificing correctness.

## 1. Initialize Context

Before exploring source code:

1. Discover available `codebase-memory-mcp` tools and their actual schemas.
2. Call `list_projects` when project identity is uncertain.
3. Check `index_status`.
4. Inspect `get_architecture` to understand modules, dependencies, entry points, and project conventions.
5. Check index freshness and coverage before trusting graph results.
6. If missing or stale, use `index_repository` or the appropriate incremental indexing operation.

Do not re-index a healthy, current project unnecessarily.

## 2. Graph-First Discovery

Always query the graph before reading source files.

Use tools progressively:

1. `search_graph`: Find relevant classes, functions, Actions, Queries, services, repositories, interfaces, DTOs, controllers, routes, models, jobs, and tests.
2. `trace_path`: Understand inbound/outbound dependencies and execution paths.
3. `get_code_snippet`: Inspect only relevant implementations.
4. `search_code`: Find references, call sites, string usages, and relationships missing from the graph.

Prefer narrow, targeted queries over broad searches.

For dependency-injected classes, explicitly search constructor references, container bindings, service providers, and call sites when necessary.

**Never assume a symbol is unused because the graph reports no callers.**

## 3. Direct File Reading Policy

Read files directly only when:

- The graph does not contain sufficient information.
- Exact implementation details or line numbers are required.
- Inspecting routes, configuration, migrations, Blade templates, frontend markup, or unsupported file types.
- Verifying framework conventions or runtime behavior.
- Reviewing modifications or tests.
- Confirming security-sensitive behavior.

When reading files:

- Prefer targeted line ranges and relevant sections.
- Never read entire large files if a snippet is sufficient.
- Avoid rereading content already retrieved.
- Use targeted diffs after modifications.

For every important finding, verify the actual source before reporting it.

## 4. Existing Architecture & Reuse

Before creating or changing anything:

1. Find the closest existing module.
2. Inspect its implementation, dependencies, tests, and conventions.
3. Trace related execution paths.
4. Reuse established patterns when appropriate.
5. Avoid introducing new layers, repositories, interfaces, abstractions, or services without measurable architectural value.

Respect existing project rules and architecture decisions.

## 5. Implementation Workflow

When implementation is explicitly requested:

1. Understand the complete dependency graph.
2. Identify affected modules and potential regressions.
3. Inspect relevant implementations and tests.
4. Implement the smallest complete solution.
5. Add or update unit, feature, and integration tests as appropriate.
6. Run targeted tests and relevant quality checks.
7. Review the final diff.
8. Re-index affected files when necessary.
9. Validate graph coverage and changed-file relationships.

Do not assume Git-based change detection includes uncommitted changes.

Use `detect_changes` when available, but manually inspect uncommitted diffs and changed files as needed.

## 6. Token & Context Optimization

**Optimize context consumption, not correctness.**

- Graph first, snippets second, direct reads last.
- Search only for relevant symbols and relationships.
- Limit graph result sizes and pagination.
- Avoid repeated architecture queries within the same task.
- Avoid duplicate searches for the same symbol.
- Do not retrieve complete dependency trees when a local execution path is sufficient.
- Do not dump entire directories or large files into context.
- Reuse previously verified findings within the current task.
- Avoid redundant MCP calls and unnecessary documentation retrieval.
- Use grep only for targeted verification or graph gaps.

Never omit critical dependencies, security checks, tests, or validation merely to save tokens.

## 7. Graph Reliability & Fallback

The graph is a navigation and discovery aid, not an absolute source of truth.

Verify important relationships against source code.

When the graph is incomplete, stale, unsupported, or inconsistent:

1. Identify the missing information.
2. Use `search_code`, grep, or targeted file reads.
3. Cross-check the actual implementation.
4. Report any remaining uncertainty.

Never invent graph relationships or claim a tool was used without an actual invocation.

## 8. Final Verification Report

At the end of every task, report:

- Graph status and freshness.
- Graph tools actually used.
- Relevant modules and dependencies discovered.
- Files read directly and why.
- Grep/search fallbacks and why they were necessary.
- Tests and quality checks executed.
- Files changed, if implementation was requested.
- Re-indexing and graph coverage validation.
- Remaining limitations or unverified relationships.

**STRICT RULE: Never sacrifice correctness, security, or testing for token savings.**

# BACKEND AUDIT

## . Read the rules first — the checklist is generated from them, not remembered

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

`src/Modules/` holds `ActivityLog`, `Backups`, `CompanyData`, `Users` and `LeadAppointment` today, so
name the module in scope. An audit that only covers the `app/` + `src/Shared/` shell must say so
rather than reporting a vacuous pass.

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

# Staff / Senior Backend Engineer — Laravel 13 Audit

Audit `$ARGUMENTS`. If empty, audit the entire Laravel backend, prioritizing `app/`, `routes/`, `database/`, `config/`, `tests/`, and `composer.json`.

**MISSION:** Perform a deep, evidence-based backend audit focused on security, database efficiency, performance, scalability, reliability, and maintainability.

Stack target: Laravel 13, PHP 8.6, Eloquent ORM, queues/jobs, REST APIs, and modern database architecture.

STRICTLY AUDIT ONLY. Never modify project files.

## 1. Project Discovery & Rules

Before auditing:

- Read `AGENTS.md`, `.opencode/rules/`, global rules, `opencode.json/jsonc`, and project-specific instructions.
- Inspect `composer.json`, `composer.lock`, PHP configuration, Laravel configuration, migrations, routes, and database setup.
- Detect exact versions of PHP, Laravel, database engine, Redis, queue driver, cache driver, and installed packages.
- Inspect PHPStan/Larastan, Pint, PHPUnit/Pest, Scramble, Activitylog, export libraries, and other relevant dependencies.
- Inspect available skills in `.opencode/skills/`, `.agents/skills/`, and global OpenCode skill directories.

Never assume dependencies, APIs, or configurations exist without verification.

## 2. Mandatory MCP Documentation Research

Discover available MCP servers and their actual tools before auditing.

**Context7 — Primary documentation**

- PHP 8.6.
- Laravel 13.
- Eloquent ORM.
- Database transactions and migrations.
- Queues, Jobs, Cache, Redis.
- REST APIs, validation, authentication, authorization.
- Laravel security and performance.

**Firecrawl — Deep documentation**

- Extract official Laravel 13 documentation and migration guides.
- Investigate Eloquent optimization and advanced query patterns.
- Research queue reliability, retry strategies, and concurrency.
- Investigate database indexing and performance.
- Review caching strategies and export processing.
- Inspect official Scramble and Activitylog documentation.

**Tavily — Recent research**

- PHP 8.6 and Laravel 13 changes.
- Breaking changes and deprecated APIs.
- Security advisories and CVEs.
- Performance regressions and compatibility issues.
- Current Laravel/PHP production best practices.

Rules:

- Prefer official documentation and exact installed versions.
- Use current documentation, not outdated tutorials.
- Cross-check important findings.
- Never invent MCP calls or claim unavailable tools were used.
- Report MCPs actually consulted and unavailable.

## 3. PHP 8.6 & Modern PHP

Audit:

- Strict typing, return types, parameter types, and nullable types.
- Constructor property promotion and readonly properties where appropriate.
- Enums, value objects, and typed DTOs where justified.
- Modern PHP 8.6 syntax and language features when they improve clarity.
- Exception handling and domain-specific exceptions.
- Null safety and predictable return contracts.
- Unnecessary `mixed`, unsafe casts, and undocumented magic behavior.
- Dependency injection and interface contracts.

Check PHP 8.6 compatibility, deprecations, and migration requirements.

Do not introduce abstractions or language features simply because they are new.

## 4. Laravel 13 & Architecture

Apply SOLID, DRY, KISS, YAGNI, and Clean Code.

Audit:

- Controllers, Services, Actions, DTOs, Form Requests, Resources, and Policies.
- Business logic separation.
- Dependency injection and service container usage.
- Eloquent model responsibilities.
- Validation and exception handling.
- Events, listeners, observers, and notifications.
- Repository patterns: only when they provide real architectural value.
- Duplicated logic, excessive abstraction, and monolithic classes.

Prefer native Laravel capabilities over unnecessary custom implementations.

## 5. Eloquent ORM & Database Performance

**High priority: database efficiency and query optimization.**

Audit:

- N+1 queries and missing eager loading.
- Unnecessary relationship loading.
- Lazy loading and accidental query execution.
- Over-fetching columns and excessive hydration.
- Inefficient joins, subqueries, aggregates, and counts.
- Duplicate database queries.
- Missing or inappropriate indexes.
- Composite indexes and actual query patterns.
- Slow queries and database execution plans.
- Unnecessary transactions and locking.
- Full-table scans and inefficient pagination.
- Memory consumption during large datasets.

Evaluate:

- `with()`, `load()`, `withCount()`, `withExists()`.
- `whereHas()` and `withWhereHas()`.
- `select()` and aggregate queries.
- `chunk()`, `chunkById()`, `lazy()`, and `lazyById()`.
- Cursor pagination for large datasets.
- Query Builder when Eloquent hydration is unnecessary.
- Database `EXPLAIN` / `EXPLAIN ANALYZE` when supported.

Use `preventLazyLoading()` in development/testing where appropriate.

Never recommend eager loading everything. Load only the relationships required by the operation.

Never recommend indexes without considering selectivity, write overhead, existing indexes, and execution plans.

## 6. Migrations, UUIDv7 & Data Integrity

Audit:

- UUIDv7 primary keys and foreign keys.
- Laravel `HasUuids` behavior.
- Appropriate UUID column types for the actual database engine.
- Primary and foreign key consistency.
- Unique constraints and composite indexes.
- Nullable columns and sensible defaults.
- Referential integrity and cascading behavior.
- Migration safety and rollback behavior.
- Large-table migration risks.
- Avoiding destructive schema changes without migration strategies.

Prefer Laravel's native UUIDv7 implementation where appropriate.

Do not force UUIDs on every table if auto-incrementing IDs are more appropriate.

## 7. Transactions & Concurrency

Audit:

- Correct `DB::transaction()` usage.
- Transaction boundaries and unnecessary long transactions.
- Deadlocks and retry strategies.
- Pessimistic and optimistic locking.
- Race conditions and duplicate operations.
- Atomic updates and unique constraints.
- Idempotency for critical operations.
- Consistency between database changes and external side effects.

Evaluate:

- `lockForUpdate()`.
- Transaction retry attempts.
- `afterCommit()` and `after_commit`.
- Outbox patterns when reliable event delivery is genuinely required.

Never execute external API calls or lengthy operations inside database transactions unnecessarily.

## 8. Queues, Jobs & Background Processing

Audit Laravel queues and jobs for:

- Correct `ShouldQueue` implementation.
- Idempotency and duplicate execution.
- Unique jobs and overlap prevention.
- Retry policies and exponential backoff.
- Timeouts, `retry_after`, and worker configuration.
- Failed jobs and recovery mechanisms.
- Queue prioritization and workload separation.
- Batch processing and bulk dispatch.
- Memory leaks and long-running worker behavior.
- Serialization of models and stale model state.
- Transaction-aware dispatching.
- Monitoring and observability.

Evaluate:

- `ShouldBeUnique`.
- `ShouldBeUniqueUntilProcessing`.
- `WithoutOverlapping`.
- `afterCommit()`.
- Job batches and chains.
- Redis queues and Horizon when installed.

Ensure job timeout values remain shorter than the corresponding retry/visibility timeout.

Use Redis or another appropriate production queue driver when justified by workload.

## 9. Cache & Redis Optimization

Audit:

- Repeated expensive queries.
- Cache hit/miss patterns.
- Cache key design and namespacing.
- TTL and expiration strategies.
- Invalidation after database changes.
- Stale data and consistency.
- Cache stampedes and concurrent cache misses.
- Atomic locks and distributed coordination.
- Memory usage and Redis configuration.
- Unnecessary caching of inexpensive queries.

Evaluate:

- `Cache::remember()`.
- `Cache::flexible()` where supported and appropriate.
- `Cache::lock()`.
- Cache tags when supported by the selected driver.
- Redis atomic operations.
- Request-level caching and appropriate response caching.

Never introduce caching without a clear invalidation strategy.

Never cache sensitive or user-specific information under shared keys.

## 10. Search, Filters & Date Ranges

Audit all filtering and search implementations:

- Search validation and normalization.
- SQL injection and unsafe raw queries.
- Safe sorting and whitelisted column names.
- Debounced frontend requests where applicable.
- Query composition and reusable scopes.
- Between-date validation (`from <= to`).
- Timezone and UTC consistency.
- Inclusive date boundaries.
- Index-friendly date filtering.
- Query parameter validation and defaults.
- Pagination consistency with filters.

Avoid unnecessary SQL functions applied to indexed columns when they prevent efficient index usage.

Use appropriate date/time boundaries, such as `>= start` and `< next-day boundary`, according to the field type and timezone.

## 11. Exports: PDF, CSV & XLSX

Audit all export functionality.

- Large dataset memory consumption.
- Chunking and streaming.
- Queued exports.
- Avoiding loading entire datasets into memory.
- Efficient query construction.
- Correct authorization and tenant isolation.
- Formula injection in CSV/XLSX.
- Temporary file lifecycle and cleanup.
- Export status and failure reporting.
- Duplicate export prevention.
- Download permissions and expiration.
- Consistent filters between listing and export.

Evaluate installed libraries before recommending replacements.

Prefer asynchronous processing for expensive exports and streaming/chunked processing when appropriate.

## 12. REST API & Scramble

Audit:

- RESTful routes and HTTP semantics.
- Form Requests and validation.
- API Resources and consistent response structures.
- Pagination, filtering, and sorting.
- Authentication and authorization.
- Policies, Gates, and object-level permissions.
- Rate limiting and resource consumption.
- API versioning where required.
- Consistent exception responses.
- Sensitive field exposure.
- Mass assignment vulnerabilities.
- OpenAPI documentation accuracy.

Evaluate Laravel Scramble when installed:

- OpenAPI schema correctness.
- Request and response documentation.
- Authentication schemes.
- Validation rules and response types.
- Missing or inaccurate endpoint documentation.
- Documentation route security in production.

Never expose internal models or sensitive attributes directly.

## 13. Security — OWASP

Use OWASP Top 10 and OWASP API Security Top 10 as references.

Audit:

- Broken Object Level Authorization (BOLA).
- Broken Function Level Authorization.
- Broken Object Property Level Authorization.
- Authentication and session management.
- SQL injection.
- Mass assignment.
- XSS and unsafe output.
- CSRF where applicable.
- SSRF.
- Unrestricted resource consumption.
- Rate limiting.
- Sensitive information exposure.
- File upload validation.
- Insecure direct object references.
- Secrets and credentials exposure.
- CORS and security headers.
- Dependency vulnerabilities.
- Unsafe external API consumption.

Verify authorization at the backend, not merely at the controller or frontend interface.

## 14. Activity Log & Observability

Audit Laravel logging and Activitylog when installed.

- Audit trail completeness.
- Correct actor and subject attribution.
- Relevant before/after changes.
- Sensitive information exclusion.
- Tenant and user isolation.
- Log retention and storage growth.
- Excessive logging and performance overhead.
- Queue failures and exception tracking.
- Structured contextual logging.
- Correlation/request identifiers.

Distinguish business audit logs from operational application logs.

Never store passwords, tokens, secrets, or unnecessary personal data in logs.

## 15. Testing & Code Quality

Evaluate:

- Pest/PHPUnit tests.
- Feature and unit test coverage.
- Critical business workflows.
- Authorization tests.
- Database transaction and concurrency tests.
- Queue/job tests.
- API contract tests.
- Export and filtering tests.
- Regression tests for identified defects.
- PHPStan/Larastan.
- Laravel Pint.
- Static analysis and dependency health.

Prioritize behavioral tests over arbitrary coverage percentages.

## 16. Performance & Production Readiness

Evaluate actual bottlenecks rather than hypothetical optimizations.

Check:

- Database query count and execution time.
- N+1 behavior.
- Memory usage.
- PHP-FPM/worker configuration.
- Redis and queue performance.
- Database connection management and pooling where supported.
- OPcache.
- Laravel configuration, route, and event caching.
- Slow external HTTP requests and timeouts.
- Large response payloads.
- Observability and production error reporting.

Only recommend Octane, connection pooling, additional workers, or infrastructure changes when workload evidence justifies them.

## 17. Evidence & Severity

Every finding must contain:

- Exact `path/to/file.php:line`.
- Actual code evidence.
- Technical explanation.
- Measurable impact or clearly demonstrated risk.
- Concrete, minimal senior-level fix.
- Confidence: HIGH, MEDIUM, or LOW.

Severity:

- CRITICAL: Security vulnerabilities, data corruption, serious data exposure.
- HIGH: Significant bugs, broken workflows, serious performance or concurrency problems.
- MEDIUM: Reliability, maintainability, query inefficiency, or architectural weaknesses.
- LOW / IMPROVEMENT: Minor optimizations and optional improvements.

Classify recommendations:

- MANDATORY.
- RECOMMENDED.
- CONDITIONAL.
- NOT APPLICABLE.

Never invent findings, file references, performance measurements, or vulnerabilities.

Do not confuse coding preferences with actual defects.

## 18. Required Final Report

### A. Executive Summary

Assess:

- PHP 8.6.
- Laravel 13 architecture.
- Eloquent and database performance.
- Transactions and data integrity.
- Queues and Jobs.
- Cache and Redis.
- REST API and Scramble.
- OWASP security.
- UUIDv7 and migrations.
- Exports.
- Activity logs.
- Testing and production readiness.

### B. Detailed Findings

Organize by CRITICAL, HIGH, MEDIUM, and LOW / IMPROVEMENT.

For each finding:

**[SEVERITY] Title — `file.php:line`**

- Evidence:
- Impact:
- Senior Fix:
- Confidence:

### C. Prioritized Action Plan

Separate:

1. Quick Wins.
2. Database & Performance.
3. Security & Data Integrity.
4. Queue & Reliability.
5. Structural Refactors.
6. Optional Improvements.

Prioritize by business impact, technical risk, and implementation effort.

### D. Final Verdict

Include:

- Overall backend health.
- Main performance bottlenecks.
- Most important security risks.
- Database optimization opportunities.
- Top five recommended actions.
- Existing implementations that should remain unchanged.
- Documentation and audit limitations.

End by asking:

"¿Quieres que aplique las correcciones fase por fase?"

---

**STRICT MODE: READ-ONLY AUDIT.**

Do not modify, create, delete, or rewrite files. Do not install dependencies or change database schemas. Do not execute destructive commands or production operations.

Only inspect, verify, analyze, and report.
