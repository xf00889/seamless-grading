# AGENTS.md

## Project
School Grading Workflow System

## Stack
- Laravel 13
- PHP 8.3+
- Blade
- Livewire 4
- Tailwind CSS
- MySQL 8+
- Spatie Laravel Permission
- Laravel Excel
- PHPUnit 12 for testing
- Vite for frontend asset bundling

## Product scope
This repository is for the MVP only.

Included in MVP:
- Authentication
- Role-based access
- Role-based sidebar navigation
- Academic setup
- Teacher load assignment
- SF1 import
- Teacher quarterly grade entry
- Draft, submit, return, approve workflow
- SF9 generation/export
- Audit logs
- Lock and reopen flow

Not included in MVP:
- Full SF10 workflow
- Registrar editing flows
- Advanced analytics
- Nonessential notifications
- Multi-school tenancy
- Unrequested phase 2 features

## Roles
- admin
- teacher
- adviser
- registrar

## Core engineering rules
- Follow SOLID principles strictly.
- Controllers must stay thin.
- Livewire components must stay thin.
- Business logic must not live in controllers, Blade views, or Livewire components.
- Use Form Requests for validation.
- Use Policies for authorization.
- Use route model binding.
- Prefer constructor dependency injection.
- Use enums for workflow statuses and typed domain values where appropriate.
- Keep classes focused on one responsibility.
- Never hardcode role checks in Blade or controllers when a policy or permission can be used.
- Use Spatie permissions and seed roles/permissions properly.
- Every major workflow change must have feature tests.
- Do not start unrelated features.
- Do not implement phase 2 items unless explicitly requested.

## Security and secure coding rules
- Follow secure-by-default coding practices.
- Never trust user input, uploaded files, route parameters, query strings, headers, or client-side state.
- Validate all incoming data with Form Requests or dedicated validators before processing.
- Authorize every protected action with Policies, Gates, middleware, or permissions.
- Do not rely on hidden UI elements as a security control.
- Use CSRF protection for all state-changing requests.
- Escape output by default and do not render raw HTML unless it is explicitly sanitized and required.
- Prevent mass-assignment vulnerabilities by using guarded or fillable properties correctly.
- Never expose secrets, tokens, API keys, passwords, or internal system details in code, logs, views, or error messages.
- Do not commit `.env` values, private keys, credentials, or generated secrets.
- Use Laravel hashing for passwords and never store plain-text passwords.
- Use signed URLs, temporary URLs, or access-controlled download flows where sensitive files are involved.
- Treat file uploads as untrusted input and validate MIME type, extension, size, and processing rules.
- Store uploads in controlled locations and never execute uploaded content.
- Sanitize file names and avoid trusting original client-provided names.
- Do not use `eval`, shell execution, raw unserialize on untrusted data, or unsafe dynamic class resolution.
- Avoid raw SQL unless absolutely necessary. If raw SQL is required, use parameter binding only.
- Do not concatenate user input into SQL, shell commands, HTML, JavaScript, URLs, or file paths.
- Prevent IDOR vulnerabilities by always scoping records to the authenticated user's allowed access.
- Enforce rate limiting, throttling, or protective controls where authentication, imports, and heavy actions are exposed.
- Use secure defaults for cookies, sessions, auth flows, and password resets.
- Log important security-relevant actions without logging sensitive payload values.
- Fail safely. On authorization or validation failure, return proper Laravel responses without leaking internal details.

## Laravel coding standards
- Follow current Laravel conventions and idiomatic framework patterns.
- Prefer framework-native solutions before custom abstractions.
- Use Form Requests for validation.
- Use Policies for authorization.
- Use route model binding where appropriate.
- Keep controllers thin and focused on request/response handling.
- Keep business logic out of controllers, Blade views, and Livewire components.
- Use Actions for write operations, state transitions, and business processes.
- Use Services for orchestration, reusable domain logic, or integrations.
- Use Eloquent relationships clearly and consistently.
- Prefer named routes and route groups.
- Use resourceful controllers and REST-style route naming where it fits.
- Use dependency injection instead of manual container lookups whenever possible.
- Use Laravel collections, helpers, casts, scopes, and built-in features where they improve clarity.
- Use database transactions for multi-step writes that must succeed or fail together.
- Use queued jobs only when explicitly needed and when failure/retry behavior is clear.
- Use events/listeners only where they genuinely improve separation, not as unnecessary indirection.
- Keep config in config files, not scattered magic values in application code.
- Avoid duplicated logic across controllers, components, requests, actions, and services.
- Prefer explicit, readable code over clever abstractions.

## Query security and performance rules
- Write secure and optimized queries by default.
- Always scope data access to the authenticated user's allowed records.
- Prefer Eloquent or Query Builder over raw SQL.
- If raw SQL is required, use bound parameters and document why it is necessary.
- Select only the columns needed; avoid `select *` unless there is a clear reason.
- Eager load required relationships to prevent N+1 query problems.
- Do not eager load unnecessary relationships.
- Use pagination for large tables and admin indexes.
- Use chunking, lazy collections, cursors, or batch processing for large imports and exports where appropriate.
- Use database indexes for frequently filtered, joined, sorted, or uniqueness-constrained columns.
- Avoid querying inside loops.
- Avoid loading entire datasets into memory when only summaries, counts, or paginated results are needed.
- Prefer database-side filtering, aggregation, and existence checks over in-memory filtering on large datasets.
- Use `exists()` instead of `count()` when only existence matters.
- Use transactions for workflows that write to multiple related tables.
- Consider locking or conflict protection where concurrent updates can corrupt workflow state.
- Review query plans for complex reports, import validation, and export generation paths.
- Keep read queries simple, predictable, and properly scoped.

## Secure coding style and review checklist
- Every new feature must be reviewed for validation, authorization, data exposure, and unsafe query behavior.
- Before finishing any task, verify:
  - all inputs are validated
  - all protected actions are authorized
  - no sensitive data is exposed in responses, logs, or exceptions
  - no raw unparameterized SQL was introduced
  - no query-in-loop or obvious N+1 issue was introduced
  - no inline secrets or credentials were introduced
  - file handling is validated and access controlled
  - output rendering is escaped or sanitized appropriately
- Prefer explicit names, explicit types, explicit state transitions, and predictable control flow.
- Do not silence errors unless there is a documented reason.
- Do not add dead code, speculative abstractions, or security theater.
- If a tradeoff is made between speed and safety, choose safety unless explicitly instructed otherwise.

## Architecture rules
- Use an Action class for every write operation, workflow transition, or business process.
- Use an Action class for each business workflow process or state transition.
- Examples of processes that require Actions:
  - import SF1 batch
  - confirm SF1 batch
  - save grade draft
  - submit grades
  - return submission
  - approve submission
  - lock grading period
  - reopen locked submission
  - generate SF9
  - export grading sheet
  - activate template
  - assign teacher load
  - update learner enrollment status
- Use Services only for shared domain logic, orchestration, integrations, complex reusable workflows, or reusable cross-action behavior.
- Simple read-only CRUD index/show pages do not need Actions unless the query logic is complex or reused.
- Prefer one clear Action per process rather than large multi-purpose Actions.
- Keep domain rules centralized and reusable.
- Do not duplicate workflow rules across controllers, components, and views.

## Code organization
- `app/Actions` for focused workflow actions
- `app/Services` for business logic, orchestration, and integrations
- `app/Enums` for statuses and typed constants
- `app/Http/Requests` for validation
- `app/Policies` for authorization
- `app/Livewire` for interactive workflow screens when needed
- `app/ViewModels` or `app/Support` may be used for complex read models if needed
- Prefer Blade pages for simple CRUD screens
- Prefer Livewire 4 for interactive workflow screens such as grade entry, import preview, approval queues, and consolidation screens

## Frontend asset rules
- Use external CSS and external JavaScript files only.
- Strictly no inline CSS.
- Strictly no inline JavaScript.
- Strictly no internal page-level `<style>` or `<script>` blocks in Blade, Livewire, or layout files.
- Do not use `style=""` attributes.
- Do not use `onclick`, `onchange`, or other inline DOM event attributes.
- Put styles in versioned asset files managed by Vite.
- Put JavaScript in versioned asset files managed by Vite.
- Blade and Livewire templates must remain markup-first and free of embedded behavior.
- If page-specific behavior is needed, create a dedicated JS module and load it through the asset pipeline.
- Avoid Alpine-driven inline behavior in templates unless explicitly approved.
- Prefer external JS modules initialized from classes, IDs, or data attributes.
- Do not add internal CSS or JS to Blade partials, components, or layouts.

## UI rules
- Prefer clean CRUD and workflow screens over fancy UI.
- Every list page should support search, filters, pagination, and status badges where relevant.
- Use consistent status chips across the app.
- Grade entry screens should prioritize usability, inline validation, sticky actions where needed, and clear workflow state visibility.
- Keep layouts role-aware and consistent.
- Use accessible forms, labels, buttons, and table structures.
- Use confirmation dialogs for destructive or state-transition actions.
- Show validation and workflow blocking reasons clearly and specifically.

## Workflow rules
- Only official roster learners can be graded.
- One submission per teacher load per grading period.
- Returned submissions must preserve adviser remarks.
- Locked submissions cannot be edited until reopened by admin.
- Approved data is the only source for official exports.
- Exports must store version and template version.
- Audit logging is required for submit, return, approve, lock, reopen, and export actions.
- Only active enrolled learners may receive grades.
- Learners marked transferred out cannot receive grades after the effective transfer date.
- Submission state transitions must be explicit and validated.
- Reopened submissions must go back through review and approval.

## Authorization rules
- Authorization must be enforced in policies, gates, middleware, or permissions.
- Do not rely on UI hiding alone for access control.
- Teachers may only access their own loads, rosters, submissions, and exports.
- Advisers may only access their own advisory sections and related submissions, consolidations, and SF9 actions.
- Admins manage setup, imports, assignments, monitoring, templates, lock/reopen, and audit visibility.
- Registrars are read-only in MVP unless explicitly expanded later.

## Database and domain rules
- Enforce foreign keys and unique constraints where applicable.
- Preserve auditability of every workflow transition.
- Prefer nullable columns only when there is a clear business reason.
- Use migrations that are explicit and reversible.
- Keep model relationships well defined.
- Avoid fat models with hidden business workflows.
- Use enums or value objects for statuses instead of scattered raw strings where practical.

## Testing rules
- Use PHPUnit 12 feature tests for role access and workflow behavior.
- Add tests for authorization, validation, and state transitions.
- Add tests for critical business rules and workflow blockers.
- Run relevant tests after each task.
- Prefer creating factories and seeders to support reliable tests.
- Add regression tests for every bug fix.
- Do not mark work complete if key tests are missing.

## Delivery rules for Codex
- For every task, apply Laravel standards, secure coding rules, and query optimization rules from this file.

For each task:
1. Read this file first.
2. Keep scope limited to the requested task.
3. Make the smallest coherent set of changes needed.
4. Respect the existing Laravel 13 and Livewire 4 setup already installed in the repo.
5. Prefer Blade for simple CRUD pages and reserve Livewire 4 for interactive workflow screens.
6. Run relevant tests and artisan commands after implementation.
7. Summarize:
   - files changed
   - commands run
   - migrations added or modified
   - tests added or updated
   - anything incomplete, risky, or assumed
8. Stop after completing the assigned task.
9. Do not start the next prompt.
10. Do not widen scope without explicit instruction.

## Self-check rules
Before finishing any task, verify:
- validation, authorization, query safety, and sensitive data exposure were reviewed
- no obvious N+1 or query-in-loop issue was introduced
- no business logic was added to controllers
- no business logic was added to Blade views
- no business logic was added to Livewire components
- no inline CSS was introduced
- no inline JS was introduced
- no `<style>` tags were introduced in Blade or Livewire templates
- no `<script>` tags were introduced in Blade or Livewire templates
- any new workflow step uses an Action class
- assets are loaded through Vite
- authorization is enforced properly
- tests cover the main behavior added by the task

## Definition of done
A task is only done when:
- the requested feature is implemented
- authorization is enforced
- validation is enforced
- workflow rules are respected
- tests are added or updated
- relevant tests pass
- no forbidden inline CSS or JS was introduced
- the task summary is complete
