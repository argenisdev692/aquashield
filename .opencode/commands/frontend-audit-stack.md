---
description: Audits frontend code against the token, TanStack Query/Table, shadcn/Base UI, TypeScript, accessibility and security rules. Produces a PASS/FAIL/WARN checklist with file:line, fixes what is unambiguous, re-verifies. The rules live in the skills — this command is only the report format and the loop.
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

# FRONTEND AUDIT

# Staff/Senior Frontend Engineer — Evidence-Based Audit

Audit `$ARGUMENTS`. If empty, audit `resources/js/`, prioritizing pages, layouts, hooks, tables, filters, forms, styles, and shared components.

**MISSION:** Perform a deep, evidence-based frontend audit. Identify real bugs, architectural weaknesses, security issues, performance bottlenecks, and maintainability problems. Do not modify files.

## 1. Project Discovery & Rules

Before auditing:

- Read `AGENTS.md`, `.opencode/rules/`, global rules, `opencode.json/jsonc`, and `components.json`.
- Inspect `package.json`, lockfiles, `composer.json`, `tsconfig.json`, `vite.config.*`, and `resources/css/app.css`.
- Detect exact installed versions and configuration: Laravel, Inertia, React, TypeScript, TanStack Table, Tailwind, shadcn/ui, React Compiler, SSR/CSR.
- Inspect available skills in `.opencode/skills/`, `.agents/skills/`, and global OpenCode skills. Load relevant skills using available native tools.
- Discover available MCPs and use Context7 for version-specific documentation, Tavily for recent changes, and Firecrawl for official migration guides when necessary.

**Never assume a dependency, API, MCP, or feature is installed or enabled. Never claim documentation was verified if it was not accessed.**

### Linting & Static Analysis

- Detect the project's existing linting tools and configuration before recommending changes.
- Check Oxlint if installed; otherwise, evaluate whether it would complement or replace the existing ESLint setup.
- Review enabled rules, plugins, TypeScript support, React and React Hooks rules.
- Identify disabled, missing, conflicting or overly permissive rules.
- Do not recommend running two overlapping linters without a clear reason.
- Never replace or modify the existing lint configuration during an audit.
-

## 1.1. Mandatory MCP Discovery & Documentation Verification

Before auditing, inspect all available MCP servers and their exposed tools.

Use the native OpenCode MCP/tool discovery capabilities to identify available integrations and their actual invocation schemas.

### Required MCPs

**1. Context7 — Primary documentation source**

Use Context7 to resolve libraries and retrieve documentation matching the exact installed versions.

Prioritize:

- Laravel 13 and Wayfinder.
- Inertia.js v3.
- React 19 and React Compiler.
- TypeScript 7.
- TanStack Table v8/v9.
- shadcn/ui.
- Tailwind CSS v4.
- Vite and SSR integrations.

**2. Tavily — Recent technical research**

Use Tavily to investigate:

- Recent releases and breaking changes.
- Deprecated APIs.
- Known bugs and compatibility issues.
- Security advisories.
- React Compiler compatibility.
- Inertia SSR hydration issues.
- TanStack Table compatibility.

Prioritize official documentation, GitHub releases, changelogs, and authoritative technical sources.

**3. Firecrawl — Deep documentation extraction**

Use Firecrawl when:

- Context7 does not provide sufficient coverage.
- Tavily identifies an authoritative migration guide.
- Complex migration or implementation details require full documentation.
- Official documentation needs deeper inspection.

### MCP Execution Rules

1. Discover available MCP tools before using them.
2. Verify their actual names, parameters, and capabilities.
3. Invoke tools using their native schemas.
4. Never invent MCP names, endpoints, parameters, or results.
5. Do not assume an MCP is configured merely because it is mentioned here.
6. Use the installed dependency versions as the primary reference.
7. Cross-check important breaking changes against official sources.
8. Avoid redundant searches when authoritative documentation already answers the question.
9. If an MCP is unavailable, use another available authoritative source.
10. Clearly report which MCPs were actually used and which were unavailable.

### Mandatory Verification Before Findings

Before reporting version-sensitive findings, verify the relevant API or behavior against documentation whenever an appropriate source is available.

Do not report deprecated APIs, compatibility problems, or migration requirements based exclusively on model knowledge.

**MCPs are research and verification tools, not permission to modify the project. The audit remains strictly read-only.**

## 2. Version-Aware Technical Audit

Apply rules only when relevant to the detected stack.

### TypeScript

- Verify `strict: true` and strict null checking.
- Detect unjustified `any`, unsafe assertions, implicit any, `@ts-ignore`, and unjustified `@ts-expect-error`.
- Audit discriminated unions, generics, event handlers, Inertia props, route typing, and table/filter schemas.
- Verify Wayfinder when installed; do not require its adoption.

### React 19 & React Compiler

- Check Rules of React, render-time mutations, derived state, effect dependencies, and component definitions.
- Detect unnecessary effects, stale closures, cascading re-renders, and excessive Context usage.
- Verify actual React Compiler configuration before evaluating manual memoization.
- Review `useActionState`, `useOptimistic`, `useEffectEvent`, `useTransition`, `useDeferredValue`, and `use()` where applicable.
- Evaluate `forwardRef`, `displayName`, `memo`, `useMemo`, and `useCallback` contextually. Never flag them automatically.

### Laravel & Inertia.js

- Verify the installed Inertia version and use its matching APIs.
- Audit navigation, partial reloads, prefetching, state preservation, deferred data, forms, HTTP requests, and error handling.
- Prefer native Inertia primitives where appropriate.
- Do not automatically flag Axios; evaluate whether it duplicates Inertia functionality.
- Review `Deferred`, `WhenVisible`, `InfiniteScroll`, `useHttp`, `useForm`, and other supported primitives when applicable.
- Evaluate optimistic updates, payload size, cancellation, and unnecessary requests.
- Inspect Laravel controllers, middleware, policies, and routes when backend verification is necessary.

### TanStack Table

- Detect v8 versus v9 before applying architecture-specific recommendations.
- For v9, evaluate `useTable`, `tableFeatures`, feature registration, subscriptions, store/atoms, and granular reactivity.
- For v8, evaluate `useReactTable`, React Compiler compatibility, and stable references.
- Audit server-side pagination, sorting, filtering, row identity, selection, column metadata, and typed schemas.
- Never apply v9 APIs or migration rules to v8 projects automatically.

### shadcn/ui & Tailwind CSS

- Inspect `components.json` and identify Radix, Base UI, or React Aria.
- Verify primitive compatibility, `data-slot`, CVA, `cn()`, semantic tokens, and component conventions.
- Check Tailwind v4 CSS-first configuration, Vite integration, theme variables, utilities, and deprecated syntax.
- Prefer semantic design tokens, but do not flag legitimate custom colors automatically.
- Check accessibility, keyboard interactions, focus states, and consistent design tokens.

## 3. Performance, Concurrency & Reliability

Audit:

- Debouncing/throttling of search and filters.
- AbortController, Inertia cancellation, and stale response protection.
- Race conditions and synchronization between URL, Inertia props, local state, and client cache.
- Date-range validation, timezone consistency, and query parameter handling.
- Event listener cleanup, timers, observers, subscriptions, and Blob URL revocation.
- Dynamic imports for heavy exports and optional features.
- Bundle size, duplicate dependencies, barrel imports, and unnecessary client-side code.
- SSR hydration, loading states, and layout shifts when applicable.

### Exports

Review PDF, CSV, and XLSX generation, dynamic imports, memory cleanup, concurrency guards, cancellation, and error feedback.

### Permissions

Review centralized permission checks, navigation flicker, and UX consistency. Verify actual authorization in Laravel policies, middleware, and routes. Frontend permissions are never security boundaries.

## 4. Additional Quality Checks

When applicable, audit:

- Accessibility: WCAG 2.2 AA, semantic HTML, keyboard navigation, ARIA, focus management, and contrast.
- Testing: Vitest/Jest, React Testing Library, Playwright, critical user flows, and missing regression tests.
- Frontend security: XSS, unsafe HTML, CSRF, token exposure, sensitive data, and insecure storage.
- Forms: validation, server errors, submission states, duplicate submissions, and usability.
- Core Web Vitals: LCP, INP, CLS, code splitting, and rendering bottlenecks.

## 5. Architecture & Code Quality

Evaluate SOLID, DRY, KISS, and YAGNI.

Identify:

- Monolithic components mixing UI, business logic, and network orchestration.
- Unnecessary abstractions and premature optimization.
- Tight coupling between domain logic and UI primitives.
- Duplicated logic and inconsistent patterns.
- Poor separation of concerns and weak component contracts.

Only recommend refactoring when there is demonstrable technical value.

## 6. Evidence & Finding Classification

Every finding must contain:

- Exact `file:line` reference.
- Actual code snippet or observable behavior.
- Technical explanation of the problem.
- Measurable impact or clearly explained risk.
- Minimal, idiomatic fix.
- Confidence: HIGH, MEDIUM, or LOW.

Severity:

- **CRITICAL:** Security vulnerabilities, data exposure, severe application failures.
- **HIGH:** Significant bugs, race conditions, broken workflows, serious performance issues.
- **MEDIUM:** Maintainability, reliability, accessibility, or architectural problems.
- **LOW / IMPROVEMENT:** Minor optimizations and optional improvements.

Classify recommendations as MANDATORY, RECOMMENDED, CONDITIONAL, or NOT APPLICABLE.

**Important rules:**

- Never invent findings or file references.
- Never report hypothetical problems as confirmed defects.
- Never recommend upgrades solely because newer versions exist.
- Never apply latest-version documentation to older installed dependencies.
- Never flag valid code based exclusively on personal preferences.
- If no issues are found, explicitly say so.
- Distinguish verified facts from assumptions and limitations.

## 7. Required Audit Report

Organize the report as follows:

### A. Executive Summary

Overall health and assessment of:

- TypeScript and type safety.
- React and React Compiler.
- Laravel and Inertia.
- TanStack Table.
- shadcn/ui and Tailwind.
- Performance and concurrency.
- Accessibility, security, and testing.
- Architecture and maintainability.

Include compliance with project rules and consulted skills/documentation.

### B. Detailed Findings

Group by CRITICAL, HIGH, MEDIUM, and LOW / IMPROVEMENT.

For each finding:

**[SEVERITY] Title** — `path/to/file.ext:line`

- Evidence:
- Impact:
- Senior Fix:
- Confidence:

### C. Prioritized Action Plan

Separate:

1. Quick Wins.
2. Structural Refactors.
3. Security and Reliability.
4. Optional Improvements.

Prioritize by impact, risk, and implementation effort.

### D. Final Verdict

Provide:

- Overall project health.
- Most important technical risks.
- Top five recommended actions.
- Areas that are already well implemented.
- Audit limitations and unverified assumptions.

End by asking:

"¿Quieres que aplique las correcciones fase por fase?"

---

**STRICT MODE: AUDIT ONLY.**

Do not edit, create, delete, or modify any project files. Do not install dependencies. Do not execute destructive commands. Only inspect, verify, analyze, and report.

## Senior React Engineering — Deep Audit

Evaluate the codebase using senior-level engineering criteria. Do not assume problems exist; investigate, trace execution paths and provide evidence.

### 1. React Hooks & Rendering

- Analyze hook correctness, dependency arrays, stale closures, referential stability and effect lifecycle.
- Detect unnecessary, cascading and excessive re-renders.
- Identify expensive render paths and component update chains.
- Evaluate memoization (`useMemo`, `useCallback`, `memo`) based on profiling evidence, not as a default recommendation.
- Check React Compiler compatibility and avoid redundant manual optimizations when the compiler handles them.

### 2. Performance & Bundle Optimization

- Analyze bundle composition, dependency weight, duplicated packages and unused dependencies.
- Identify opportunities for code splitting, lazy loading, dynamic imports and route-level loading.
- Investigate expensive computations, rendering bottlenecks and unnecessary network requests.
- Use available profiling and bundle-analysis tools when configured.
- Justify optimizations with measurable impact or explain how they should be measured.

### 3. Memory Leaks & Resource Lifecycle

- Detect event listeners, subscriptions, observers, timers and resources that are not properly cleaned up.
- Investigate effects with incorrect cleanup or repeated resource registration.
- Analyze component unmounting, navigation and long-lived application sessions.
- Check WebSocket, SSE and external subscription lifecycle management.

### 4. Async Operations & Race Conditions

- Detect race conditions between components, requests and asynchronous operations.
- Analyze stale responses overwriting newer state.
- Check request cancellation using AbortController or the appropriate library mechanism.
- Evaluate debouncing and throttling for search, filters, autosave and high-frequency events.
- Identify duplicate requests, uncontrolled parallel operations and missing concurrency limits.
- Evaluate loading, error and cancellation states.
- Verify that asynchronous operations cannot update obsolete or unmounted component state.

### 5. State Consistency & Concurrency

- Analyze consistency between local state, server state, URL state and shared/global state.
- Detect conflicting updates, stale caches and out-of-order mutations.
- Review optimistic updates, rollback behavior and cache invalidation.
- Evaluate concurrent mutations and duplicate submissions.
- Check synchronization between components that share or depend on asynchronous data.

### 6. Production Debugging

- Trace complex failures across components, hooks, API calls and state transitions.
- Investigate error boundaries, unhandled promise rejections and silent failures.
- Identify missing observability, structured logging and diagnostic information.
- Distinguish root causes from symptoms.
- Consider production-only conditions such as network latency, retries, rapid navigation and concurrent user interactions.

### 7. Evidence & Validation

For each finding:

- Identify the affected files, components and execution paths.
- Explain the failure mechanism and its real-world impact.
- Classify severity and likelihood.
- Provide evidence from source code, profiling or reproducible behavior.
- Recommend the smallest effective correction.
- State how the correction should be tested or measured.

Do not claim performance improvements without measurements. Do not recommend debouncing, throttling, cancellation, memoization or concurrency controls indiscriminately; justify each according to the actual behavior and requirements.

Do not limit yourself to detecting suspicious patterns. Investigate execution flows, trace dependencies and side effects, and validate issues with evidence. Distinguish between potential risks, reproducible problems, and confirmed issues.
