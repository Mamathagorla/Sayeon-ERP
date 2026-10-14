# Sayeon ERP – End-to-End Business Workflows

Written for: testers, developers and business owners who need to know exactly how each business process runs through the system – who does what, which approvals exist, how statuses change, and what is written where.
Last updated: 13 Oct 2026 · Main document: [PROJECT_DOCUMENTATION.md](PROJECT_DOCUMENTATION.md)

Everything below was verified against the code and exercised by the automated suite in `tests/e2e`. Where a step you might expect does **not** exist, it is marked **Not supported**.

---

## 0. Conventions

* **Role** names: SA = Super Admin, CA = Company Admin, MGR = Manager, HR, ACC = Accountant, CO = Compliance Officer, EMP = Employee.
* **Company isolation** applies to every flow: a record outside the viewer's company is "not found". Cross-company actions (assigning, approving, linking) are refused.
* **Idempotency**: repeating a click/POST never creates a second record or a second payment (specific protections are listed per flow).
* **Notifications** are in-app (`notifications` table). Email is only sent if SMTP is configured; otherwise the screen says so.
* Every create/update/delete writes `activity_logs`.

## 1. Module dependency map

| From | Hands over to | What flows across |
|---|---|---|
| Recruitment / Onboarding | User + Employee profile | Candidate details become the login (role Employee) and the employee record (company code, manager, designation, joining date) |
| Employee profile | Attendance, Leave, Payroll, Offboarding | Everything HR does for a person hangs off the profile and its company |
| Attendance | Payroll | Absent days become Loss of Pay |
| Leave | Attendance, Payroll | Approved leave is written to attendance as `on_leave`; approved unpaid leave days become Loss of Pay |
| Payroll run | Payslip document, Payments ledger | Payslip PDF from the company template; marking the run paid books the salary payment |
| Offboarding | Employee profile, User, Payroll | Exit completed: status resigned/terminated, login disabled, employee left out of later payroll |
| Purchase order | Bill | An approved order can be billed; its payment status then follows the bill |
| Bill / Invoice | Payments ledger | Payments out (bills) and in (invoices) |
| Expense claim | Payments ledger | Reimbursement books a cash-out payment |
| Payments ledger, invoices, bills | Reports | Receivables, payables, cash flow, financial summary, P&L |
| Meeting action item | Task | Conversion creates the task; completing the task completes the item |
| Compliance item | Next compliance cycle | Filing (or the daily generator) creates the next period once |
| Document template (per company and type) | Generated document archive | Offer, joining, relieving, payslip, invoice and similar documents; archived and immutable once issued |

---

## 2. Employee lifecycle: Recruitment → Onboarding → Employee → Attendance → Leave → Payroll → Offboarding

In the application the employee record is created **by** onboarding (when a candidate has joined), so the order is *Onboarding → Employee → Attendance → Payroll → Offboarding*. An employee can also be created directly by HR for an existing login.

### 2.1 Recruitment and onboarding
Tests: `RecruitmentToEmployeeFlowTest`, `OnboardingCandidateProfileFlowTest`

**Starting conditions:** company, department, a reporting manager who has an employee profile in the same company, an HR user. The candidate has no ERP login.
**Owners:** HR and Super Admin only (Company Admin, Manager and Accountant can view and complete their own checklist items).

| # | Action (role) | Route | Status change | Side effects |
|---|---|---|---|---|
| 1 | Add candidate (HR) – name, email, phone, source, department, designation, reporting manager | `POST hr/onboarding` | → `candidate` | Manager notified (`onboarding_assigned`) |
| 2 | Select candidate (HR) | `POST hr/onboarding/{id}/select` | `candidate` → `selected_for_onboarding` | – |
| 3 | Send portal link (HR) | `…/send-portal-link` | → `link_sent`, verification `pending` | 48-char token valid 7 days; mailed only if SMTP configured, otherwise the link is shown on screen. A new link invalidates the old one. After the offer is sent HR can re-issue the link without changing the stage |
| 4 | Candidate submits details (public portal, token only) | `POST onboarding/portal/{id}/{token}/verify` | → `details_submitted`, verification `submitted` | A second submit is refused until HR asks for a correction. Role-specific form/answers are stored in the `onboarding_candidate_*` tables |
| 5 | HR starts review | `…/start-review` | → `hr_review` | – |
| 6 | HR decision | `…/verification/review` (`approved` / `rejected` / `correction_needed`) | `approved` → **approved** (verification `verified`); `rejected` → **rejected** (closed); `correction_needed` → back to `link_sent` with HR's note | Reviewer and time recorded |
| 7 | Send offer (HR) | `…/send-offer` | `approved` → `offer_sent` | `offer_sent_at`; offer letter reachable by the candidate through the token – **requires an active Offer Letter template for the company** |
| 8 | Candidate responds | `POST onboarding/portal/{id}/{token}/offer-response` | `accepted` → `offer_accepted` (default onboarding checklist created once); `rejected` → `withdrawn` | Only possible after HR approval and while the token is valid |
| 9 | HR advances the pre-joining stages | `…/status` (one defined next step at a time) | `offer_accepted` → `documents_pending` → `bgv` → `hr_verification` → `ready_to_join` → `joining` → `joined` | Skipping a stage is refused; timestamps (`hr_verified_at`, `joined_at`) set |
| 10 | Create employee record (HR) | `…/create-employee` (allowed from `joined`, `induction`, `probation`, `confirmed`) | – | One transaction: user (role Employee, temporary password shown once) + employee profile (company-prefixed code, manager, designation, joining date) + link to the onboarding record. Existing login in the same company is linked; in another company is refused; repeating is a no-op |
| 11 | Post-joining | `…/status` | `joined` → `induction` → `probation` → `confirmed` | Manager notified of each stage |
| – | Withdraw (HR) | `…/withdraw` | any open stage → `withdrawn` (not from `rejected`, `withdrawn`, `confirmed`) | – |

**Rejection / cancellation paths:** HR rejects (→ `rejected`), HR requests correction (loop back to step 3's state), candidate declines (→ `withdrawn`), HR withdraws. A rejected or withdrawn candidate cannot be offered, advanced or turned into an employee.
**Errors covered:** invalid email/name, wrong/expired token, offer response before approval, manager from another company, other company's HR, roles without permission.

### 2.2 Employee record
Tests: `EmployeeAttendanceLeaveFlowTest`

HR/Company Admin create a profile for an existing login (`POST hr/employees`): unique login and unique employee code, company in the creator's scope, reporting manager in the **same company**, DOB not in the future. Employees, Managers, Accountants and Compliance cannot create. Lists show only the viewer's company.

### 2.3 Attendance
* Employee self check-in / check-out (`hr/attendance/check-in|check-out`): check-in once a day, check-out only after check-in and once. Super Admin has no self-service.
* HR marks/corrects a day (`hr/attendance/mark`) – one row per employee per day, own company only, status from: present, absent, half_day, on_leave, holiday, week_off.
* HR CSV bulk upload (`hr/attendance/bulk-upload`) – unknown/other-company employee codes and bad rows are skipped and counted.
* Attendance feeds Payroll (absent days) and the employee profile/dashboard KPIs.

### 2.4 Leave
| Step | Rule |
|---|---|
| Apply (EMP, HR, CA; **not** Manager/Super Admin) | Valid leave type; end ≥ start; days = calendar days inclusive; **no overlap** with a pending/approved request; **paid types must fit the annual quota** (pending + approved + this request); unpaid leave is not capped. Reporting manager is notified |
| Decide (`approve` / `reject`) | Only a pending request; approver must have `leave.approve`, be in the same company, outrank the requester (SA > CA > HR = MGR > ACC/CO/EMP) and not be the requester (Super Admin excepted). Requester is notified once |
| Result of approval | Every day of the request is written to attendance as `on_leave` (a day HR had marked `absent` is corrected; present / half-day / holiday / week-off rows are kept) |
| Cancel | Only the owner, only while pending |
Statuses: `pending` → `approved` \| `rejected` \| `cancelled`.

### 2.5 Payroll and payslip
Tests: `PayrollPayslipFlowTest`

1. **Salary structure** (`POST hr/payroll/structure/{userId}` – HR, CA): basic, HRA, allowances, deductions; saving again updates the single row; own company only.
2. **Generate** (`POST hr/payroll/generate`, month 1–12, year 2000–2100 – HR, CA; Super Admin creates one run per company):
   * payable = active employees of the company with a salary structure;
   * `gross = basic + HRA + allowances`; **LOP days = absent attendance days + approved unpaid-leave days** in the month; `LOP amount = gross ÷ days-in-month × LOP days`; `net = gross − deductions − LOP`;
   * one run per **company + month + year** (a repeat is refused: "already exists"); one payslip per employee; every employee notified once; all in one transaction.
3. **Payslip view/PDF**: the employee sees their own; HR/CA see their company's; others and other companies are refused. The PDF **requires an active Payslip template**; generated once and re-served while the data is unchanged.
4. **Mark paid** (`POST hr/payroll/{id}/mark-paid` – HR, CA): run `processed` → `paid`, and **one cash-out payment per company** (method `payroll`, reference `PAYROLL-YYYY-MM`) is booked in the payments ledger. Repeating changes nothing. Salary payments cannot be deleted from the invoice/bill screens. Another company cannot mark the run paid.
Statuses: `processed` → `paid`. Resigned/terminated employees are not paid.

### 2.6 Offboarding
Tests: `OffboardingExitFlowTest`

| Step | Detail |
|---|---|
| Start | EMP files a resignation for themself (`POST hr/offboarding`, type forced to *resignation*, only one open exit); HR files a resignation or termination for any employee of their company. 11 checklist items are created; reporting manager notified |
| Checklist (`…/tasks/{id}`) | Each item has an owner role: Manager (review, handover acceptance, department clearance), HR (approval, exit interview, final documents), Employee (handover notes, asset return), Admin (asset confirmation, access revocation), Accountant (final settlement). Only the owner role – or HR/Super Admin – can update an item; statuses `pending`, `in_progress`, `completed`, `skipped`. The record's stage moves forward automatically with the checklist |
| Stages | `resignation_submitted` → `manager_review` → `hr_approval` → `exit_initiated` → `handover` → `department_clearances` → `asset_return` → `access_revocation` → `final_settlement` → `exit_interview` → `final_documents` → `exit_completed`; side exit `rescinded` |
| Close (`…/status` = `exit_completed`, HR/SA only) | Allowed only when **every checklist item is completed or skipped**. Then: employee profile → `resigned` or `terminated`, `users.status` → `inactive` (login disabled), `completed_at` set, employee and manager notified. History (payslips, leave, tasks) is kept |
| Final states | `exit_completed` and `rescinded` are final – no further stage changes |
| Documents | Relieving/exit letters need an active template for the company |
| Not supported | Final settlement is a checklist item only – no amounts, payment or accounting entry |

---

## 3. Purchase → Approval → Order → Payment → Accounting
Tests: `ProcurementToPayableFlowTest`

There is **one** purchase document: the purchase order, which starts life as a request (`pending`). There is no separate purchase-request entity and no goods-receipt document.

| # | Action (role) | Route | Status change | Side effects / rules |
|---|---|---|---|---|
| 0 | Maintain vendors (CA) | `vendors` | – | Per company; a vendor with orders cannot be deleted (set inactive) |
| 1 | Request a purchase (EMP, MGR, ACC, CA) – vendor, items (qty × unit price) | `POST purchase-orders` | approval `pending`, payment `pending`, no fulfillment | `PO-YYYY-0001`; total computed server-side; vendor must be in the same company; invalid/blank items refused |
| 2 | Edit while pending (CA) | `…/{id}/edit` | – | Items replaced, not appended; approved/rejected orders cannot be edited |
| 3 | Approve or reject (ACC, CA – holders of `purchase_order.approve`) | `…/approve`, `…/reject` | `pending` → `approved` (fulfillment `ordered`) or `rejected` | **Requester cannot decide their own request** (Super Admin excepted); another company cannot; decided once; requester notified |
| 4 | Track fulfillment (CA) | `…/status` | `ordered` → `in_transit` → `received` | Only on approved orders; moves forward only (a received order is not taken back) |
| 5 | Record the vendor's invoice as a **Bill** (ACC, CA) and link it to the approved order of the same company | `accounting/bills` (field *Purchase order*) | bill `unpaid` | GST totals; bill number unique |
| 6 | Pay the bill (ACC, CA) | `accounting/bills/{id}/payments` | bill `unpaid` → `partially_paid` → `paid` (`overdue` set automatically after the due date) | Payment (direction *out*) in the ledger; cannot exceed the open balance or be posted on a cancelled bill |
| 7 | Payment status follows Accounting | automatic | PO payment `pending` → `partial` → `paid` | Derived from the linked bills; cannot be flagged by hand once a bill is linked |
Delete: an approved order can only be deleted while it has not progressed – no linked bills, fulfillment still `ordered`, payment still `pending`. Pending and rejected orders can be deleted by Company Admin.

**Accounting outputs:** bill/payables list, payments ledger, Payables, Financial Summary and P&L reports.
**Gaps:** payment status of an order with no linked bill is still a manual flag (D-13); no three-way match enforcement.

---

## 4. Task → Assignment → Review → Completion
Tests: `TaskLifecycleFlowTest`

| # | Action | Rules |
|---|---|---|
| 1 | Create task (MGR, CA, EMP, SA) | Title, company, department, **project of the same company**, priority (low/medium/high/urgent), optional assignee **in the same company**, dates; code like `SAY-T001`; UUID assigned; status history row written |
| 2 | Assignment | Assignee notified (not when assigning yourself); re-assigning notifies only the new assignee |
| 3 | Work | `POST tasks/{id}/status`: `new`, `assigned`, `in_progress`, `waiting`, `review`, `completed`, `on_hold`, `cancelled`. Every change is recorded (from → to, who, when); `completed_at` is set on completion and cleared if the task is re-opened |
| 4 | Review | `review` is a status only. There is **no reviewer role or approve/reject action** – anyone with task edit rights in the company can move a task to or out of `review`/`completed` |
| 5 | Reminders | Open tasks due within 2 days → `task_due_soon`; past due → `task_overdue` (once a day per task). Completed/cancelled tasks never remind |
| 6 | Comments, checklist, attachments | Company-scoped; empty comments refused |
| 7 | Delete (MGR, CA) | Soft delete; history kept |
Also: tasks created from meeting action items (§5) and from the To-Do list are ordinary tasks.
**Known gap (D-22):** employees only *list* their own tasks, but can open or update a colleague's task by id.

---

## 5. Meeting → Action Item → Task → Completion
Tests: `MeetingToTaskFlowTest`, `MeetingProvidersTest`

| # | Action | Rules |
|---|---|---|
| 1 | Schedule (MGR, CA, EMP, SA) | `POST meetings`; provider `none` (no link generated), Google Meet or Zoom (created before saving – a failure saves nothing); end time after start; all participants must be in the company; the organiser is always a participant |
| 2 | Invite | Each participant gets an in-app invitation (not the organiser); email only if configured; external participants by name/email, one invite per email; the same user cannot be added twice |
| 3 | Minutes | `POST meetings/{id}/mom` |
| 4 | Action items | `POST meetings/{id}/action-items`: description, optional assignee **who belongs to the meeting's company**, due date; status `pending` → `in_progress` → `completed` (other values refused) |
| 5 | Convert to task (needs `task.create`) | `…/action-items/{item}/convert` with **department and project of the meeting's company**: one transaction creates the task (`Follow-up: …`, same company/assignee/due date, status `new`, history row) and links it to the item; assignee is notified. Converting twice is refused; without a project/department nothing is created |
| 6 | Completion | Completing the linked task sets the action item to `completed`; re-opening the task re-opens the item |
Visibility: an employee reaches a meeting (view, minutes, participants, action items) only if they organised or were invited; the join link needs the meeting's token. Another company never sees it.
**Not supported:** meeting "completed" state (a meeting is a dated record plus minutes).

---

## 6. Compliance → Reminder → Submission → Completion
Tests: `ComplianceCycleFlowTest`

| # | Action (role) | Rules |
|---|---|---|
| 1 | Create item (CO, CA, SA) | Company, compliance type, title, regulator, period label, **due date**, recurrence (none / monthly / quarterly / half-yearly / annually), **responsible person in the same company**, reminder lead days (default 7). Status `pending`; responsible person notified |
| 2 | Reminder | Within the lead time: `compliance_due_soon`; after the due date the list flips the item to `overdue` and `compliance_overdue` is sent – once a day, only to the responsible person, never for filed items |
| 3 | Submission – "Mark filed" (CO, CA) | `POST compliance/{id}/mark-filed` → status `filed`, `filed_at` stamped; filing an already-filed item keeps the original time |
| 4 | Next cycle | Filing a recurring item creates the next cycle once (due date advanced by the recurrence, status `pending`, period label left blank). A daily generator (and *Generate recurring* button) pre-creates cycles for items inside their lead time; filing and the generator never create two successors |
| 5 | Completion | `filed` is the end state of a cycle; history chain via `previous_item_id` |
Statuses: `pending` → (`overdue` automatically) → `filed`. **Not supported:** reviewer/approval step; `in_progress` cannot be set from the UI.

---

## 7. Invoice and accounting
Tests: `InvoiceToCashFlowTest`

| # | Action | Rules |
|---|---|---|
| 1 | Create invoice (ACC, CA, SA) | Unique number (per-company sequence suggested), customer name + country (+ state), items, discount %, GST %. **Server computes**: subtotal, discount, taxable value, tax. Same state as the selling company → CGST + SGST; different state → IGST (a posted GST type cannot override this); total rounded to 2 decimals; oversize totals refused |
| 2 | Status | `draft`, `sent`, `partially_paid`, `paid`, `overdue`, `cancelled`. Payments drive `partially_paid`/`paid`; `overdue` is set automatically for open invoices past their due date. **No approval step** |
| 3 | Record payment | Amount > 0 and ≤ open balance; not on cancelled invoices; payment keeps the invoice's company; removing a payment recomputes the status |
| 4 | Edit | If the total changes after payments the status is re-evaluated |
| 5 | Delete | Refused when payments exist (cancel instead) |
| 6 | Documents | PDF/print: built-in layout, or the company's Invoice template when active (archived) |
| 7 | Reports | Cash in/out and net cash flow from the payments ledger; outstanding receivables/payables = open `amount − payments`; P&L (accrual, by month); Income vs Expense |
Other payments in the same ledger: bill payments (out), salary runs (out), expense-claim reimbursements (out).

---

## 8. Expenses
Tests: `ExpenseSubscriptionFlowTest`

### 8.1 Company expenses / subscriptions
Create/edit/cancel expenses (ACC, CA): vendor, category, billing cycle (monthly/quarterly/yearly/one_time), amount, renewal date, status `active`/`cancelled`. No approval. Renewal reminders (`expense_due_soon` within 7 days, `expense_overdue` after) go to the creator once a day. **Recurring templates** (monthly/quarterly/yearly; end by occurrences or end date) generate expense rows exactly once per occurrence, catching up missed periods; templates can be paused, resumed, cancelled; a run is limited to the viewer's company (Super Admin: all).

### 8.2 Expense claims (employee reimbursement)
| # | Action (role) | Route | Status | Rules |
|---|---|---|---|---|
| 1 | Submit (any role with `expense_claim.create`) | `POST expense-claims` | `submitted` | Title, category, amount > 0, date not in the future; claimant must have an employee profile; company = claimant's; identical waiting claim refused; reporting manager notified |
| 2 | Cancel (claimant) | `…/cancel` | → `cancelled` | Only while `submitted` |
| 3 | Approve / reject (MGR, HR, ACC, CA) | `…/approve`, `…/reject` (optional note) | `submitted` → `approved` / `rejected` | Same company; not your own claim; once only; claimant notified |
| 4 | Reimburse (ACC, CA) | `…/reimburse` | `approved` → `reimbursed` | One transaction: cash-out payment (reference `CLAIM-{id}`, method as entered) + claim linked to it (`payment_id`); not your own claim; cannot be done twice; claimant notified |
Visibility: claimants see their own claims; approvers/Finance see their company's.
**Not supported:** receipt upload; posting to the P&L (cash ledger only).

---

## 9. Document generation
Tests: `DocumentGenerationFlowTest`

1. **Template configuration** (CA, CO with `document.edit`): per company and document type, versioned; file type html / docx / pdf; flagged auto-detected fields must be reviewed; a **draft must be previewed** before activation; activating a version retires the previous one – exactly one active version per company + type.
2. **Data mapping**: placeholders such as `{{employee_name}}`, `{{designation}}`, `{{joining_date}}`, `{{annual_salary}}`, `{{net_pay}}`, `{{period}}` are filled from the ERP record. A template that uses an unknown field, or has no place for the record's identity, is refused – nothing is printed blank.
3. **Generation**: via the ERP action (offer/joining letter, payslip, relieving letter, increment letter, invoice, bill, PO, expense report) or `documents/render/{type}/{id}`. Only the record's own company template is used. Missing required data produces a "complete these details" message.
4. **Download and archive**: the PDF/DOCX is stored (`generated_documents`: template version, data snapshot, hash, user). Unchanged data re-serves the stored file byte-for-byte; changed data or a new template version creates a **new** document and never alters an issued one.
5. **Rules**: HR letters/certificates/payslips are **template-only** (no template → blocked); accounting documents fall back to the built-in layout. Templates that produced documents cannot be deleted; an active template must be deactivated first; other companies cannot see or change them.

---

## 10. Access control and isolation
Tests: `AccessControlAndIsolationTest`

* Login with valid credentials sets role, permissions and (for Super Admin) the "all companies" scope; wrong password and unknown email give the same message; deactivated or deleted users cannot log in.
* Every protected URL (GET and POST) redirects anonymous visitors to the login.
* A role × module matrix test confirms each role reaches exactly the screens its permissions grant.
* User and role administration is Super Admin only; the company switcher is Super Admin only.
* List screens (vendors, expenses, compliance, projects, customers, meetings, invoices, campaigns, websites, employees, tasks) show nothing from other companies – including for a Company Admin account that has no employee profile yet.

---

## 11. Coverage summary

| Workflow | Test file | Result at last run |
|---|---|---|
| Recruitment & onboarding → employee | `RecruitmentToEmployeeFlowTest`, `OnboardingCandidateProfileFlowTest` | Pass |
| Employee, attendance, leave | `EmployeeAttendanceLeaveFlowTest` | Pass |
| Payroll & payslip | `PayrollPayslipFlowTest` | Pass |
| Offboarding | `OffboardingExitFlowTest` | Pass |
| Purchase → payment | `ProcurementToPayableFlowTest` | Pass (1 open item: D-13) |
| Invoice → cash → reports | `InvoiceToCashFlowTest` | Pass |
| Expenses (subscriptions) | `ExpenseSubscriptionFlowTest` | Pass |
| Expense claims | verified by a one-off scenario (submit → approve/reject → reimburse → ledger); **no permanent test file yet** | Pass (not in suite) |
| Task | `TaskLifecycleFlowTest` | Pass (1 open item: D-22) |
| Meeting → task | `MeetingToTaskFlowTest`, `MeetingProvidersTest` | Pass |
| Compliance | `ComplianceCycleFlowTest` | Pass |
| Document generation | `DocumentGenerationFlowTest` | Pass |
| Access control & isolation | `AccessControlAndIsolationTest` | Pass |

## 12. Test data and preconditions
`E2ETestCase::world()` builds two companies (Alpha/ALP, Beta/BET) each with Company Admin, Manager (reporting manager of the employee), HR, Accountant, Compliance Officer, Employee, plus a Super Admin and a department; extra helpers create salary structures, projects, templates and logins. Reference data (roles, permissions, countries, states, leave types, compliance types, phases) comes from the database clone. All test users use the password `Test@123`.

## 13. Status transition quick reference

| Entity | Statuses (→ = allowed move) |
|---|---|
| Onboarding record | candidate → selected_for_onboarding → link_sent → details_submitted → hr_review → approved → offer_sent → offer_accepted → documents_pending → bgv → hr_verification → ready_to_join → joining → joined → induction → probation → confirmed; side exits: rejected, withdrawn; correction_needed returns to link_sent |
| Leave | pending → approved \| rejected \| cancelled |
| Payroll run | processed → paid |
| Offboarding | resignation_submitted → … → exit_completed (needs checklist done); side exit rescinded; both final |
| Purchase order | approval: pending → approved \| rejected; fulfillment: ordered → in_transit → received; payment: pending → partial → paid (from bills) |
| Bill | unpaid → partially_paid → paid (+ overdue, cancelled) |
| Invoice | draft → sent → partially_paid → paid (+ overdue, cancelled) |
| Expense claim | submitted → approved → reimbursed; submitted → rejected \| cancelled |
| Task | new, assigned, in_progress, waiting, review, completed, on_hold, cancelled (history kept) |
| Meeting action item | pending → in_progress → completed |
| Compliance item | pending → overdue → filed |
| Recurring expense/meeting | active ⇄ paused; active → completed \| cancelled |

## 14. Defects found and fixed
Found by the end-to-end review (October 2026) and fixed in the code:

| Area | Problem fixed |
|---|---|
| Payroll | Payroll was global: one company's run for a month blocked all others. Now per company (migration). Any company could mark another's run paid – now scoped. Marking paid now books the salary payment |
| Accounting | Overpayment, payments on cancelled documents, deleting invoices/bills with payments (which silently deleted the payments), stale status after editing a paid invoice, receivables overstated by part-paid invoices |
| Meetings → tasks | Converting an action item to a task always failed (no project); assignee not notified; action item did not follow task completion; unvalidated status/assignee; duplicate participant crashed; uninvited employees could edit a meeting they could not open |
| Offboarding | Exit could be closed with open checklist items, a closed exit could be re-opened, the leaver kept a working login |
| Leave | No overlap check, no quota check, unknown leave type crashed, approved leave did not appear in attendance |
| Employees / compliance | Reporting manager or responsible person from another company accepted; a filed item's filing time could be overwritten |
| Tasks | Re-opening a task left the completion date |
| Isolation | A Company Admin without an employee profile saw projects, invoices, campaigns and websites of every company |
| Purchase | Orders could be un-received, deleted after payment, or flagged paid independent of Accounting (now linked to bills) |
| Onboarding | An expired offer link could not be re-issued |

Open items are listed in §11 of the main document.
