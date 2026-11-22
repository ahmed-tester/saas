# Working Rules for the Store Management SaaS

## Before Making Changes

- Inspect the current state and share your findings and proposed changes with the project owner. Wait for explicit approval of the specific changes before editing files, changing configuration or data, installing dependencies, or running commands that change project state.
- A general request to investigate, fix, or improve something authorizes read-only investigation until the project owner approves the proposed changes.

## Implementation Scope

- Follow the [Phase 1 implementation plan](<خطة تنفيذ SaaS إدارة المحلات — المرحلة الأولى (تعليمات للمنفّذ) (2).md>). Use the rules in Section 8, the implementation order in Section 9, and the acceptance criteria in Section 10.
- Consult the project owner on open decisions and any changes to the scope, dependencies, or dependency versions.

## Project Documentation

- Write project documentation in Arabic. Keep file names, code identifiers, and commands in their original form.
- Use `README.md` as the entry point. Explain what the project does, who it serves, and how to run it and its tests locally. Link to the project documentation.
- Use `docs/roadmap.md` to record the work completed, the current work, and the next step. Link each Phase 1 step to its acceptance criteria and record its status and latest verification. Mark a step complete when its acceptance criteria have been verified.
- Use `docs/architecture.md` to explain the request flow, tenant data isolation, and how the Super Admin panel operates on a selected tenant.
- Use `docs/decisions.md` to record decisions and their rationale, along with open questions for the project owner.
- Use the Phase 1 implementation plan as the reference for scope and acceptance criteria. Update the affected documentation in the same pull request when a task changes setup, system behavior, or roadmap status.

## Code Organization

- Keep controllers and Filament actions focused on handling requests and delegating work. Use Form Requests for validation in controllers, and group business logic in services by domain, such as `app/Services/Billing`.
- Use clear English names and enums for fixed statuses and types.
- Keep user-facing text in Laravel translation files. Add or update each translation key in both Arabic (`lang/ar`) and English (`lang/en`) in the same change. Both languages are required for Phase 1.
- Follow `.editorconfig` and use Laravel Pint to format PHP code.

## Tenant Data Isolation

- Derive `tenant_id` from the current tenant context. Reject access to tenant data when the context is missing, and exclude `tenant_id` from request input and `$fillable`.
- Scope tenant data queries, validation rules, and permission checks to the current tenant. Follow the Super Admin exceptions defined in the plan, and scope administrative operations on a tenant's data to the tenant selected for the operation.

## Data Integrity

- Use a database transaction for operations that must succeed or roll back as a unit. Send any resulting email or notification after the transaction commits.
- Use `decimal` for monetary amounts and store timestamps in UTC.
- Once a migration has been merged, make further schema changes in a new, reversible migration.

## Before Delivery

- Run the PHPUnit tests relevant to the change and check formatting with `vendor/bin/pint --test` from the Laravel application directory.
- Verify MySQL-dependent behavior, such as concurrent sequence generation, against a dedicated MySQL test database. The current PHPUnit configuration uses in-memory SQLite.
- Report the files changed, the checks run and their results, and any outstanding verification or incomplete work with the reason.
