# AGENTS.md

## Project
School Grading Workflow System

## Stack
- Laravel 11
- PHP 8.3+
- Blade
- Livewire 4
- Tailwind CSS
- MySQL 8+
- Spatie Laravel Permission
- Laravel Excel
- Pest for testing
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
- Use Pest feature tests for role access and workflow behavior.
- Add tests for authorization, validation, and state transitions.
- Add tests for critical business rules and workflow blockers.
- Run relevant tests after each task.
- Prefer creating factories and seeders to support reliable tests.
- Add regression tests for every bug fix.
- Do not mark work complete if key tests are missing.

## Delivery rules for Codex
For each task:
1. Read this file first.
2. Keep scope limited to the requested task.
3. Make the smallest coherent set of changes needed.
4. Respect the existing Laravel 11 and Livewire 4 setup already installed in the repo.
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