# TAWZEE AI — PROJECT CONTEXT PACKAGE

Version: 2026-09-26
Purpose: Handoff / compact project context only.
This file is NOT the primary source of truth.

If anything here conflicts with repository files, use the repository files first.

Primary sources, in priority order:
1. AGENTS.md
2. ARCHITECTURE.md
3. PROJECT.md
4. TASKS.md
5. SYSTEM_PROMPT.md
6. Local skills
7. PROJECT_CONTEXT.md

TASKS.md remains the task/status source of truth.

==================================================
PROJECT CORE — MUST KEEP
==================================================

Project name:
Tawzee AI

Product:
Arabic-first RTL commercial SaaS for wholesale/distribution businesses,
initially focused on Yemen.

Main domains:
- Companies / users
- Customers
- Products / categories
- Warehouses / inventory
- Sales / invoices
- Receivables / collections
- Reports / dashboard

Architecture:
- Laravel 12
- Modular monolith
- Blade
- Livewire
- Tailwind CSS
- Shared-schema multi-tenancy using company_id
- Arabic-first / RTL
- Integer minor units for monetary values
- No float for financial calculations

Verified environment:
- Windows
- VS Code + Codex
- PHP 8.2.12
- Livewire 4.4.5
- MariaDB 10.4.32
- XAMPP used to run MariaDB through MySQL control
- PostgreSQL mandatory before final launch at P8-T08

Project path:
D:\projects\laravel_projects\TAWZEE

Databases:
- Development: tawzee_dev
- Testing: tawzee_test

Git:
Repository:
https://github.com/jazemmohammed7-stack/tawzee-ai

Branch:
main

Workflow rule:
Do not commit/push a task until its Codex report has been reviewed.

==================================================
WORKING RULES — MUST KEEP
==================================================

Work on ONE task only.

Standard workflow:

1. Read repository sources.
2. Inspect current implementation.
3. Implement one TASKS.md task only.
4. Run focused tests.
5. Run required regression/full tests.
6. Codex returns a report.
7. Review report.
8. Only then:
   - git status
   - git add .
   - git diff --cached --check
   - git commit
   - git push
9. Start the next task.

Never install a new package without explicit approval.

Unexpected migration/schema changes require review first.

Never claim verification that was not actually executed.

Never use destructive database commands:

- migrate:fresh
- migrate:reset
- migrate:refresh
- db:wipe
- rollback
- truncate
- drop

PostgreSQL verification is deferred to P8-T08.

MariaDB success is NOT proof of PostgreSQL compatibility.

==================================================
TENANCY — MUST KEEP
==================================================

CurrentCompany is the trusted tenant context.

company_id must never be trusted from:

- URL
- query string
- request body
- headers
- cookies
- Livewire public state

Tenant resource protection includes:

- BelongsToCompany
- CompanyScope
- CompanyBuilder
- CurrentCompany

User and Company intentionally are not globally tenant-scoped where
such scoping would break authentication/bootstrap flows.

HTTP tenancy:

- SetCurrentCompany establishes trusted company context.
- RequireCompany rejects company impersonation attempts.
- EnsureCompanyReady prevents commercial functionality before setup completion.

Company status:

New companies remain pending_setup until later initialization in P4-T08.

==================================================
AUTHENTICATION — MUST KEEP
==================================================

Implemented:

- Company registration
- Login
- Logout
- Password reset
- Rate limiting
- Disabled-user protection
- Session protection

Disabled users cannot authenticate.

Founder identity is explicitly stored using:

founder_user_id

Never infer founder from:

- email
- first user
- smallest ID
- creation order

==================================================
ACTIVITY LOGGING — MUST KEEP
==================================================

P1-T07 implemented secure tenant activity logging.

Core pieces:

- activity_logs
- ActivityLog
- ActivityLogBuilder
- RecordActivity

Properties:

- Tenant-scoped
- company_id derived from CurrentCompany
- Actor derived from trusted auth context
- Cross-company actor/subject rejected
- Sensitive properties recursively sanitized
- No automatic Request/session/header dump
- Append-only at application/Eloquent layer

Protected sensitive examples:

- passwords
- reset/auth/session tokens
- cookies
- Authorization
- CSRF/XSRF
- APP_KEY
- DB credentials
- SMTP credentials
- API secrets

Transactional rule:

Required audit records must participate in the same business transaction.

Current confirmed event:

company.registered

==================================================
AUTHORIZATION — MUST KEEP
==================================================

Package:

spatie/laravel-permission 6.25.0

Reason:

Compatible with Laravel 12 and PHP 8.2.

Teams:
Enabled.

Team key:
company_id

Team context:
Derived from CurrentCompany.

Do NOT use:

Gate::before

as an Owner bypass.

==================================================
PERMISSIONS / ROLES — MUST KEEP
==================================================

Permission enum:
19 permissions matching PROJECT.md.

Default roles:

- owner
- admin
- sales
- warehouse
- accountant
- viewer

DefaultRole represents the exact PROJECT.md permission matrix.

InitializeCompanyRoles:

- Creates/synchronizes the six roles
- Uses current company context
- Is idempotent
- Allows the same role names in different companies
- Does not accept company_id from the client
- Assigns Owner using founder_user_id only

RegisterCompany transaction currently includes:

1. Company
2. Founder user
3. Document sequences
4. Default roles/permissions
5. Founder Owner assignment
6. company.registered activity log

Failure of required role initialization rolls back registration.

Existing companies:

May be initialized explicitly.

No blind production bulk backfill was implemented.

==================================================
AUTHORIZATION POLICY PATTERN — P2-T03
==================================================

Reusable policy foundation:

TenantPolicy

Working limited example:

DocumentSequencePolicy

Rules:

- Use Permission enum.
- Use Laravel Policies / Gate.
- Use server-side authorize().
- Do not authorize by role-name checks when permission checks are sufficient.
- Cross-tenant resources must be rejected.
- Missing CurrentCompany fails closed.
- Spatie Team/permission context must not leak between company switches.
- Hiding buttons is UX only, never the security boundary.

P2-T03 verified both HTTP and Livewire authorization patterns.

==================================================
PRODUCTION UI FOUNDATION — P2-T04
==================================================

P2-T04 introduced the first real production application interface.

A reusable App Shell was created and should be reused by future modules.

App Shell includes:

- Sidebar
- Topbar
- Main content region
- Mobile navigation/drawer
- Current company identity
- Current user identity
- Production page layout

Reusable UI/design patterns were introduced for:

- Buttons
- Fields
- Badges
- Modals/dialogs
- Alerts
- Toast notifications
- Empty states
- Loading states
- Focus states
- Disabled states

UI principles established:

- Arabic-first
- True RTL
- Enterprise SaaS style
- Desktop / Tablet / Mobile responsive
- Local Arabic font
- Consistent spacing and visual hierarchy
- Accessible focus/interaction states
- Mobile layouts should not force desktop-width tables
- Server-side authorization remains mandatory
- No fake navigation to unimplemented features
- Future screens should reuse the existing App Shell/design language instead
  of creating separate visual systems per module

Reference UX patterns were adapted from modern ERP/SaaS products such as:

- Odoo
- Zoho Inventory

These are references for interaction patterns only.

Do not copy branding, layout, or CSS verbatim.

==================================================
USER MANAGEMENT — P2-T04
==================================================

Production route/page:

/users

Implemented functionality:

- Current-company users list only
- Search by name/email
- Role filter
- Active/inactive filter
- Server-side pagination
- 10 users per page
- Desktop table
- Mobile card/list presentation
- Create user
- Edit user
- Same-company Role assignment
- Activate user
- Deactivate user
- Arabic validation
- Deactivation confirmation
- Loading states
- Empty states
- Toast/feedback behavior

Authorization:

- Uses Policy authorization
- Uses users.manage permission
- Server-side authorization in Livewire/actions
- UI visibility alone is never considered security

Tenant/IDOR protection:

- User queries are restricted to CurrentCompany
- Cross-company users cannot be listed/edited/deactivated
- Cross-company Roles cannot be assigned
- Forged company_id does not switch tenant
- Stale/tampered Livewire state is rejected

Founder/Owner protection:

- Other users cannot deactivate the Founder/Owner
- Other users cannot downgrade/change the protected Founder/Owner role
- Self-change behavior follows the current PROJECT/TASKS rules
- No founder inference through email/order/ID

No user deletion was implemented.

No custom Role editor or Permission editor was implemented.
==================================================
PROJECT STATUS
==================================================

Phase 0:
Done on MariaDB.

Phase 1:
Completed on MariaDB.

P1-T01: Done
P1-T02: Done
P1-T03: Done
P1-T04: Done
P1-T05: Done
P1-T06: Done
P1-T07: Done

Phase 2:

P2-T01:
Done
Spatie roles/permissions + Teams/company isolation.

P2-T02:
Done
Permission enum + six default roles + exact permission matrix +
InitializeCompanyRoles + Founder -> Owner assignment.

P2-T03:
Done
Policies + server-side authorize() pattern +
HTTP/Livewire verification.

P2-T04:
Done
Production User Management + reusable production App Shell.

P2-T05:
Done
Production Company Settings:
- company name
- allow_negative_stock
- company.settings authorization
- tenant isolation
- transactional ActivityLog
- reuse of P2-T04 App Shell

P2-T06:
CURRENT TASK
Comprehensive permission matrix testing for existing routes.

Do not start Phase 3 before P2-T06 is completed, reviewed,
committed and pushed.

==================================================
P2-T04 — FINAL VERIFICATION
==================================================

Focused P2-T04 tests:

46 tests / 223 assertions
PASSED

Relevant regression:

74 tests / 389 assertions
PASSED

composer test:mysql:

268 tests / 1712 assertions
PASSED

composer test:

28 tests / 68 assertions
PASSED

composer lint:

PASSED

Pint:
PASSED

PHP syntax:
110 PHP files passed

git diff --check:
PASSED

Frontend build:

npm.cmd run build
PASSED

58 modules built.

Browser verification:

Desktop:
1440px

Tablet:
768px

Mobile:
375px

Verified:

- RTL
- Sidebar
- Topbar
- Mobile navigation
- User management interactions
- Dialogs/forms
- Focus states
- Loading states
- No horizontal overflow
- No reported JavaScript console errors

Browser temporary test data was cleaned afterward.

No migration added.

No schema change.

No new package.

No PostgreSQL verification.

==================================================
CURRENT TASK — P2-T05
==================================================

Task:

P2-T05 — Company Settings

TASKS.md acceptance scope includes:

- Company settings page
- Company name
- allow_negative_stock
- Requires company.settings permission
- Changes recorded in ActivityLog
- Tenant isolation
- Reuse the P2-T04 App Shell/design system

Do NOT begin implementation based only on this summary.

Before implementing P2-T05, read:

- AGENTS.md
- PROJECT.md
- ARCHITECTURE.md
- TASKS.md
- SYSTEM_PROMPT.md
- local skills

Then inspect the actual current code.

==================================================
UI/UX STANDARD FOR P2-T05 AND FUTURE SCREENS
==================================================

Do NOT create a second unrelated design system.

Reuse:

- P2-T04 App Shell
- Sidebar
- Topbar
- existing visual tokens
- existing buttons
- existing fields
- badges
- alerts
- toast pattern
- form styles
- responsive behavior
- Arabic/RTL foundations

Future screens must continue targeting professional commercial
SaaS/ERP quality rather than default Laravel CRUD.

==================================================
EXPECTED P2-T05 SECURITY
==================================================

Exact behavior must come from PROJECT.md / TASKS.md.

Expected principles:

- company.settings permission required
- CurrentCompany is the trusted tenant source
- Client-supplied company_id must not choose the company
- Company A cannot modify Company B
- Changes must be authorized server-side
- Settings mutations should be transactional when multiple writes are involved
- Required ActivityLog records should participate in the same transaction
- Sensitive/unnecessary data must not be logged

Do not invent additional settings outside the task.

==================================================
P2-T05 OUT OF SCOPE
==================================================

Do not start:

- P2-T06
- Customers
- Products
- Warehouses
- Inventory
- Sales
- Invoices
- Collections
- Reports
- Dashboard business metrics
- PostgreSQL migration
- Custom Role editor
- Permission editor

==================================================
IMPORTANT OPERATIONAL HISTORY
==================================================

MariaDB may be stopped after Codex finishes verification.

If PHPUnit reports:

"Test database connection failed; check local credentials privately."

first verify that XAMPP MySQL/MariaDB is running.

Do not immediately modify application code.

Codex authentication history:

Codex previously produced:

401 Unauthorized / Incorrect API key

Checks showed:

OPENAI_API_KEY:
not set in Process/User/Machine environment.

CODEX_API_KEY:
not set in Process/User/Machine environment.

Old:

~/.codex/auth.json

was isolated as:

auth.backup.json

Codex was then signed in again using ChatGPT authentication.

A simple Codex request succeeded afterward.

This is operational history only.

It is NOT a project architecture decision.

==================================================
CONTEXT MANAGEMENT RULE — MUST KEEP
==================================================

This PROJECT_CONTEXT.md is a compact handoff file.

It must be updated by DELTA.

Do NOT repeatedly summarize this summary.

When a task is completed:

1. Read actual repository sources first.
2. Update PROJECT STATUS.
3. Replace CURRENT HANDOFF/current-task information.
4. Add only new long-term architecture decisions.
5. Preserve stable core information.
6. Remove obsolete temporary task details.
7. Remove superseded test/log information when it no longer adds value.
8. Never override repository Source of Truth.

Do not permanently retain:

- Long terminal logs
- Repeated explanations
- Temporary debugging output
- Intermediate failures that no longer affect future work
- Old prompts for completed tasks
- Old test counts when replaced by a newer authoritative verification
- Conversation filler
==================================================
CURRENT HANDOFF
==================================================

Last completed task:

P2-T05 — Company Settings.

Status:
DONE

Delivered:

- Production route:
  /company/settings

- Editable fields:
  name
  allow_negative_stock

- Authorization:
  company.settings

- Authorization enforced:
  - page access
  - Livewire save
  - application Action

- Tenant source:
  CurrentCompany only

- Forged company identifiers rejected.
- Stale/tampered Livewire state rejected.

Activity logging:

Event:
company.settings.updated

Behavior:
- records before/after only for changed fields
- no log when nothing changed
- company update + activity record use one transaction
- rollback verified on failure

UI:
- Reuses P2-T04 App Shell
- Reuses existing Design System
- Updated company name reflected in Topbar/Sidebar/mobile drawer
- Arabic RTL
- Responsive

Database/schema:
- No migration
- No schema change
- No new package
- PostgreSQL not tested

Final verification:

CompanySettingsTest:
37 tests / 220 assertions

Relevant regression:
138 tests / 668 assertions

composer test:mysql:
305 tests / 1932 assertions

composer test:
28 tests / 68 assertions

composer lint:
PASSED
115 PHP files

git diff --check:
PASSED

npm build:
PASSED
58 modules

Browser verification:
PASSED at 1440 / 768 / 375 px

Verified:
- RTL
- navigation
- keyboard interaction
- validation
- loading
- toast
- company-name refresh
- persisted values after reload
- no horizontal overflow
- no reported JavaScript errors

Current task:

P2-T06 — Comprehensive permission matrix tests for existing routes.

P2-T06 has NOT started.

NEXT ACTION:

1. Commit/push P2-T05.
2. Confirm clean Git state.
3. Start P2-T06 only.
4. Do not start Phase 3 before P2-T06 is reviewed and committed.