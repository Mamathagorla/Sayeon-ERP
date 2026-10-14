# Sayeon ERP – Project Documentation

*Centralized Business Operations Management System for Sanvima, Sayeon, Festive, Nihira and Intrepid.*
Written for: developers, testers and administrators who build, run, test and deploy the system.
Last updated: 13 Oct 2026 · Companion document: [E2E_BUSINESS_WORKFLOWS.md](E2E_BUSINESS_WORKFLOWS.md)

---

## 1. What the system is

A multi-company ERP. One installation serves several companies; every business record belongs to exactly one company and users only see their own company's data (Super Admin sees all). It covers HR (employees, onboarding, attendance, leave, payroll, performance, offboarding), purchasing, accounting (invoices, bills, payments, reports), expenses, tasks/projects, meetings, compliance, documents and templates, policies, support, marketing and website records.

| Item | Value |
|---|---|
| Framework | CodeIgniter 4.7 (PHP 8.1+; developed on 8.2) with a modular (HMVC-style) layout |
| Database | MySQL 8 locally (`erp_db`, port 3307); MariaDB on the live host (`u160045728_sayeon`) |
| PDF | Dompdf (generated documents, invoices, payslips, reports) |
| Spreadsheet | PhpSpreadsheet |
| UI | Server-rendered PHP views, Bootstrap/AdminLTE style, `public/assets/css/sayeon-theme.css` |
| Live site | https://sayeon.sanvima.net/ |

## 2. Repository layout

```
app/
  Config/            Routes.php (includes every module's Routes.php), Filters.php, Events.php, Services.php …
  Controllers/       BaseController (company scoping, activity log, templated documents), FileController (gated downloads)
  Helpers/           permission_helper (can()), gst_helper, payroll_helper, localization_helper, company_code_helper
  Libraries/         FileStorage (local / S3 adapters), FormBreadcrumbs
  Commands/          CLI jobs (see §9)
  Database/          core migrations (roles, permissions, users, notifications, activity logs) and seeders
  Modules/<Name>/    Controllers, Models, Services, Views, Routes.php, Database/Migrations (+ Seeds)
  Views/layouts/     main.php (sidebar, topbar, flash messages)
public/              index.php (front controller), assets/
writable/            logs, cache, session, uploads (company_documents, document_templates, generated_documents …)
tests/_support/      E2E harness (base class, bootstrap, phpunit.phar)
tests/e2e/           End-to-end workflow tests
docs/                this documentation
```

### Modules

| Module | Purpose | Main tables |
|---|---|---|
| Auth | Login, profile, users, roles, localization settings | users, roles, permissions, role_permissions, app_settings |
| Company | Companies, directors, bank accounts, countries/states | companies, company_directors, bank_accounts, countries, states |
| Department | Departments | departments |
| Customer | Customers per company | customers |
| HR | Employees, attendance, leave, payroll, performance, increment letters | employee_profiles, attendance, leave_types, leave_requests, salary_structures, payroll_runs, payslips, review_cycles, performance_reviews, employee_increment_letters |
| Onboarding | Recruitment pipeline, candidate portal, role-specific candidate form + HR evaluation, onboarding checklist | onboarding_records, onboarding_tasks, onboarding_phases, onboarding_candidate_*, onboarding_role_groups/questions/eval_criteria, onboarding_evaluations |
| Offboarding | Resignation/termination, exit checklist | offboarding_records, offboarding_tasks, offboarding_phases |
| Task | Projects and tasks (checklists, comments, attachments, status history) | projects, tasks, task_* |
| Meeting | Meetings, participants, minutes, action items, recurring meetings, Google Meet / Zoom | meetings, meeting_participants, meeting_action_items, recurring_meetings, google_oauth_connections |
| Compliance | Compliance items, recurring cycles, per-company applicable types | compliance_items, compliance_types, company_compliance_types, compliance_attachments |
| Purchase | Vendors, purchase requests/orders | vendors, purchase_orders, purchase_order_items |
| Accounting | Invoices (GST), bills, payments | invoices, invoice_items, bills, bill_items, payments |
| Expense | Subscriptions/expenses, recurring expenses, **expense claims** | expenses, expense_categories, recurring_expenses, expense_claims |
| Document | Company document store, **template-based document generation** and archive | documents, document_templates, generated_documents |
| Report | Task, compliance, company, expense, receivables, payables, financial, P&L, income-vs-expense, campaign reports | – (reads other modules) |
| Notification | In-app notifications, due-date reminders, mail service | notifications |
| Dashboard / Calendar / Search | Role dashboards, calendar feed, live search | – |
| Policy, Support, Todo, Marketing, Website | Policies, support tickets, personal to-dos, campaigns, website/domain records | policies, support_tickets, todos, campaigns, websites |

## 3. Roles, permissions and company isolation

### 3.1 Roles
`super_admin`, `admin` (Company Admin), `manager` (Project Manager), `hr` (HR Manager), `accountant`, `compliance_officer`, `employee`.

### 3.2 How access is enforced (three layers)
1. **Login filter** (`auth`) on every route except login, assets and the candidate portal.
2. **Route permission filter** – each route declares `permission:<module>.<action>`; the user's permissions are loaded into the session at login. Super Admin bypasses this check.
3. **Controller rules** – company scope (`outOfScope()` / `companyScopeFor()` in `BaseController`), ownership rules (nobody approves their own request), and role rules (e.g. only HR/Super Admin move onboarding stages).

A record outside the viewer's company behaves as *not found*. A company-scoped account that has no employee profile yet is scoped to "no company" and sees **nothing** (not everything).

### 3.3 Permission matrix (V = view, C = create, E = edit, D = delete, A = approve, R = reimburse)

| Module | Company Admin | Manager | HR | Accountant | Compliance | Employee |
|---|---|---|---|---|---|---|
| attendance | CV | – | CEV | – | – | CV |
| leave | ACV | – | ACEV | – | – | CV |
| payroll | CEV | – | CEV | – | – | V (own payslips) |
| employee | CDEV | – | CDEV | – | – | – |
| onboarding | EV | EV | CDEV | EV | – | V (own) |
| offboarding | EV | EV | CDEV | EV | – | CEV (own exit) |
| task | CDEV | CDEV | – | – | – | CEV |
| project | CEV | CDEV | – | – | – | V |
| meeting | CDEV | CDEV | – | – | – | CEV |
| compliance | CEV | – | – | – | CDEV | – |
| purchase_order | ACDEV | CV | – | ACV | – | CV |
| invoice / bill / customer | CDEV | – | – | CDEV | – | – |
| expense | CDEV | – | – | CDEV | – | – |
| expense_claim | ACRV | ACV | ACV | ACRV | CV | CV |
| document / policy | CDEV | – | – | – | CDEV | V |
| bank_account | CDEV | – | – | – | – | – |
| report | V | V | V | V | V | – |

Super Admin has every permission plus user/role/company/setting management and the "All companies / one company" switcher. The authoritative source is the `role_permissions` table (seeded by `RolePermissionSeeder`, extended by migrations).

Notes that surprise people:
* **Managers hold no leave permissions** (by design); leave approval belongs to HR / Company Admin.
* **Only HR and Super Admin move onboarding/offboarding stages** even though Company Admin/Manager/Accountant hold `.edit` (they may complete their own checklist items).
* Leave approval hierarchy: Super Admin > Company Admin > HR = Manager > Accountant / Compliance / Employee. An approver may only decide requests from a strictly lower rank, in their own company; nobody approves their own.

## 4. Database

* Migrations live in `app/Database/Migrations` and in each module's `Database/Migrations`. Run with `php spark migrate -all`.
* Every company-owned table carries `company_id` (or reaches a company through `employee_profiles`).
* Money columns are `DECIMAL(12,2)`; dates are `DATE`/`DATETIME` in the application timezone (`Asia/Kolkata`).
* **Pending migrations for live** (created 13 Oct 2026, present only on the test database until you run them):
  `HR/2026-10-13-100001_AddCompanyToPayrollRuns`, `Expense/2026-10-13-110001_CreateExpenseClaimsTable`, `Accounting/2026-10-13-120001_AddPurchaseOrderToBills`. Code for payroll runs, expense claims and bill↔PO links requires them.

## 5. Cross-cutting behaviours

| Concern | How it works |
|---|---|
| Activity log | `logActivity(module, action, recordId, description)` writes `activity_logs` after creates/updates/deletes. |
| Notifications | `NotificationService::notify()` writes in-app rows (`notifications`). Reminders are produced by a sweep run from the navbar bell (`GET /notifications/recent`, at most every 5 minutes per session) for tasks, compliance items, today's meetings, expense renewals and website expiries; one reminder per item per type per day. |
| Email | `MailService` / onboarding mail send only when SMTP host, user and password are all configured; otherwise the app reports "not configured" and shows the link/message instead. Nothing is ever claimed as sent when it was not. |
| Files | `service('fileStorage')` (local `writable/uploads` or S3). Downloads go through `files/download`, which re-checks permissions. |
| Documents from templates | HR/Offboarding/Payroll documents (offer, joining, relieving, payslip …) are **template-only**: with no active template for the record's company, generation is blocked. Accounting documents (invoice, bill, PO, expense report) fall back to the built-in layout. A company never uses another company's template. Generated files are archived and an unchanged record re-serves the archived file. |
| Localization | `app_settings` (org country, currency, timezone, locale, formats); users may override timezone/locale. |
| Numbering | Employee codes, task codes, project codes and invoice numbers use the company short code (`SAY-INV001`, `SAY-T001`); purchase orders are `PO-YYYY-0001` (global sequence). |

## 6. Business modules in brief (rules that are enforced)

* **Employees** – one profile per login; unique employee code; reporting manager must be in the same company; date of birth not in the future.
* **Attendance** – self check-in/out (once a day each), HR marking (upsert per employee/day), CSV bulk upload (`employee_code,date,status,check_in,check_out`). Statuses: present, absent, half_day, on_leave, holiday, week_off. "Late" = check-in after 10:00; "early" = check-out before 17:00.
* **Leave** – pending → approved / rejected / cancelled. Dates must not overlap another pending/approved request; paid leave types are limited by `annual_quota` (pending + approved + new); approving marks each day `on_leave` in attendance.
* **Payroll** – per company and month; Loss of Pay = absent days + approved unpaid-leave days; gross = basic + HRA + allowances; net = gross − deductions − LOP (`gross ÷ days-in-month × LOP days`). Marking a run paid books one cash-out payment per company.
* **Invoices / bills** – server-side totals (discount, CGST+SGST for same-state, IGST for different state, derived from company vs customer state), unique numbers, payments capped at the open balance, no payments on cancelled documents, documents with payments cannot be deleted (cancel instead), status re-evaluated when the total is edited.
* **Reports** – outstanding receivables/payables are `amount − payments`; cash figures come from the `payments` ledger (invoice receipts, bill payments, salary runs, expense-claim reimbursements).
* **Tasks** – every task needs a company, department and a project of the same company; assignee must be in the company; status history is recorded; `completed_at` follows the status.
* **Meetings** – provider `none` (manual), Google Meet or Zoom; participants must belong to the company; employees reach only meetings they organise or are invited to; join links use an unguessable token.
* **Compliance** – items per company/type with due date, recurrence (none, monthly, quarterly, half-yearly, annually) and reminder lead time; filing spawns the next cycle exactly once; a daily generator also pre-creates upcoming cycles.
* **Expenses** – subscription/renewal expenses with renewal reminders; recurring templates generate expense rows idempotently; separate **expense claims** (see workflows doc).

## 7. Setup

### Local development
1. PHP 8.2 with `mysqli`, `intl`, `mbstring`, `gd`, `zip`, `curl`; MySQL 8.
2. Copy `.env.example` to `.env`; set `database.default.*`, `app.baseURL`, mail and provider keys.
3. `php spark migrate -all`, then seeders (`RoleSeeder`, `PermissionSeeder`, `RolePermissionSeeder`, `CompanySeeder`, …) as needed.
4. `start-dev.bat` starts MySQL (3307), PHP-CGI workers and nginx on `http://127.0.0.1:8080`.
5. Test logins are listed in `login.txt` (shared password for test users). **Change all default passwords before going live.**

### Environment (.env) keys that matter
`CI_ENVIRONMENT`, `app.baseURL`, `database.default.*`, `encryption.key` (do **not** change on a system that already stores encrypted values – bank account numbers and website credentials), `storage.driver`, `email.*`, `meetingProviders.*` (Google/Zoom), `whatsapp.*`, `app.superAdminEmail`.

## 8. Deployment to the live site

Summary (the full step-by-step with the exact upload list was prepared separately in `C:\Users\mamat\sayeon_deploy` and `…_SENSITIVE`):
1. Back up live files and the live database.
2. Upload `app/`, `vendor/`, `public/` (including `.htaccess`), `spark`, composer files and empty `writable/` folders. **Never** upload the local `.env`, `.git`, `tests/`, `writable` contents, SQL dumps or documents.
3. Create the server `.env` from `.env.production` (base URL `https://sayeon.sanvima.net/`, live DB password, keep the existing `encryption.key`).
4. Run the database upgrade SQL (structure first, then reference data) in phpMyAdmin, or `php spark migrate -all` over SSH.
5. Make `writable/` writable; upload template files / documents that belong to synced data.
6. Smoke-test login, an employee list, an invoice PDF, a payslip PDF and an upload; check `writable/logs`.

## 9. Scheduled work

| Job | Command | Also runs automatically |
|---|---|---|
| Next compliance cycles | `php spark compliance:generate-recurring` | once a day on the first request after midnight (Events.php) |
| Recurring expenses | `php spark recurring-expenses:generate` | same |
| Recurring meetings | `php spark recurring-meetings:generate` | same |
| Template self-check | `php spark documents:selftest` | no (diagnostic; rolls back everything it creates) |

On the live host add a daily cron calling the three generate commands so nothing depends on someone opening the site.

## 10. Testing

* **Harness**: `tests/_support/E2ETestCase.php` runs real requests (routes → filters → controllers → models → MySQL) against the throw-away database `erp_e2e_test` (built from `erp_db`'s schema by `build_test_db.php`). It refuses to run on any other database, blanks SMTP credentials (no mail is sent) and empties business tables before every test.
* **Runner**: PHPUnit 10.5 phar in `tests/_support/`. Run from the project root with a scratch `phpunit.xml` whose bootstrap is `tests/_support/bootstrap.php`, one test class per PHP process.
* **Suites** (`tests/e2e`): recruitment→employee, attendance/leave, payroll/payslip, offboarding, procurement→payable, invoice→cash, expense subscriptions, tasks, meeting→task, compliance, document generation, access control and isolation, meeting providers, candidate profile.
* **Known-defect convention**: a test for a rule the code does not yet enforce reports as *incomplete* with a defect id instead of failing; it turns green automatically when fixed.
* **Environment notes (Windows)**: an antivirus may quarantine test files run from inside the project folder – run from a scratch copy. The tests replace the file-storage adapter with a copy-based one because renaming a freshly written PDF can be refused by the scanner.

## 11. Known gaps and limitations (open items)

| Id | Area | Gap |
|---|---|---|
| D-13 | Purchase | A purchase order with no linked bill still has a manually set payment status. |
| D-22 | Tasks | Employees only *list* their own tasks, but can open/update any task of their company by id. |
| – | Expenses | Claims have no receipt upload and do not feed the profit-and-loss statement (cash ledger only). |
| – | Accounting | No double-entry ledger or chart of accounts: "accounting entries" are invoices, bills and the payments ledger. Payroll cost is not part of the P&L. |
| – | Invoices | No approval step; credit notes / advances are not modelled. |
| – | Procurement | No separate goods-receipt document; "received" is a status on the order. |
| – | Meetings | No "completed" state; a meeting is a dated record with minutes. |
| – | Compliance | No reviewer/approval step; "in progress" cannot be set from the UI. |
| – | Offboarding | Final settlement is a checklist item only (no amount calculation). |

Defects found and fixed during the 2026-10 review are listed in [E2E_BUSINESS_WORKFLOWS.md](E2E_BUSINESS_WORKFLOWS.md#14-defects-found-and-fixed).
