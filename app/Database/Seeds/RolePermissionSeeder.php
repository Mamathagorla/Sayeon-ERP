<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // slug => [permission slug patterns]. '*' expands to all seeded permissions.
        $map = [
            // Company-scoped (see BaseController::companyScopeFor(['admin']))
            // across Companies/Employees/Tasks/Projects/Meetings/Expenses/Bills/Reports.
            'admin'               => [
                // company.edit (not .create/.delete — creating/removing a
                // company stays Super-Admin-only) was missing despite the
                // rest of the "manage your own company" surface already
                // being granted below (bank_account.*, document.*,
                // compliance.*) and Directors already being gated by
                // company.edit / scoped to 'admin' in DirectorController —
                // this closes that gap so the always-visible Edit button
                // and Directors tab on the company page actually work for
                // Company Admin instead of denying with no visible cause.
                'company.view', 'company.edit',
                'employee.view', 'employee.create', 'employee.edit', 'employee.delete',
                'task.view', 'task.create', 'task.edit', 'task.delete',
                // View/manage their own company's projects — no delete,
                // same pattern as compliance below (create/edit only).
                'project.view', 'project.create', 'project.edit',
                'meeting.view', 'meeting.create', 'meeting.edit', 'meeting.delete',
                'expense.view', 'expense.create', 'expense.edit', 'expense.delete',
                'bill.view', 'bill.create', 'bill.edit', 'bill.delete',
                // Same invoice.* set Accountant already holds — Admin
                // manages their own company's receivables too, not just
                // payables (bill.*) and expenses.
                'invoice.view', 'invoice.create', 'invoice.edit', 'invoice.delete',
                // Their own company's bank accounts, documents, and
                // compliance items — not in the original field list,
                // added because the disabled/read-only tabs on the
                // company page weren't useful without them. Compliance
                // stays create/edit only (no delete) per request.
                'bank_account.view', 'bank_account.create', 'bank_account.edit', 'bank_account.delete',
                'document.view', 'document.create', 'document.edit', 'document.delete',
                // Policies & Manuals — same full-CRUD level as Documents,
                // same two grantees (Company Admin, Compliance Officer).
                'policy.view', 'policy.create', 'policy.edit', 'policy.delete',
                'compliance.view', 'compliance.create', 'compliance.edit',
                // Self check-in/out only (view + create, no edit) — same
                // level as Employee, not HR's org-wide correction rights.
                'attendance.view', 'attendance.create',
                // Applies for their own leave and is the "head officer"
                // who approves their company's Managers/HR (see
                // LeaveController::ROLE_RANK) — no leave.edit, that's
                // HR's leave-type configuration, not Admin's job.
                'leave.view', 'leave.create', 'leave.approve',
                'report.view',
                // Payroll for their own company — same view/create/edit
                // level as HR (no delete), so Company Admin can generate
                // and review payroll runs without HR being provisioned.
                'payroll.view', 'payroll.create', 'payroll.edit',
                // Onboarding: Company Admin stands in for "IT/Admin" in
                // the onboarding workflow (this app has no dedicated IT
                // role) — view the pipeline, and .edit only to complete
                // their own it_access_setup/asset_setup checklist items
                // (OnboardingTaskController enforces that narrower rule;
                // this permission alone doesn't let them touch the main
                // record — see OnboardingController's owner-role check).
                'onboarding.view', 'onboarding.edit',
                // Offboarding: Company Admin handles assets, IT/access
                // and administrative clearance — view the pipeline,
                // .edit only to complete their own asset_return/
                // access_revocation checklist items (same pattern as
                // Onboarding — OffboardingTaskController enforces the
                // narrower owner-role rule; this alone doesn't let them
                // touch the overall exit status).
                'offboarding.view', 'offboarding.edit',
                // Purchase Orders/Purchases/Vendors — Company Admin is
                // the primary owner of procurement (full CRUD, plus can
                // approve/reject requests — the "administrative
                // clearance" role, same reasoning as Onboarding/
                // Offboarding's asset/IT sections).
                'purchase_order.view', 'purchase_order.create', 'purchase_order.edit',
                'purchase_order.delete', 'purchase_order.approve',
                // Help/Support — raising a ticket and viewing your own
                // needs no permission at all (see Support/Routes.php,
                // same "everyone" reasoning as Notifications); only the
                // resolver action (support_ticket.edit) and removing a
                // ticket (support_ticket.delete) are gated, and Company
                // Admin is that resolver for their own company.
                'support_ticket.edit', 'support_ticket.delete',
            ],
            'manager'             => [
                'project.view', 'project.create', 'project.edit', 'project.delete',
                'task.view', 'task.create', 'task.edit', 'task.delete',
                'meeting.view', 'meeting.create', 'meeting.edit', 'meeting.delete',
                'report.view',
                // No attendance.*/leave.* — Project Manager's menu is
                // Tasks/Projects/Meetings/Reports only. Leave approval
                // for the ranks Manager used to cover (Accountant/
                // Compliance Officer/Employee, see LeaveController::
                // ROLE_RANK) still routes through HR (same rank) or
                // Company Admin, so nothing is left unapprovable.
                // Onboarding: complete only their own induction/manager-
                // onboarding/30-60-90-review checklist items — same
                // owner-role-gated .edit as Company Admin/Accountant above.
                'onboarding.view', 'onboarding.edit',
                // Offboarding: reviews the resignation, handover and
                // team/department clearance — same view + edit-own-tasks
                // level as above; overall exit status stays HR-only.
                'offboarding.view', 'offboarding.edit',
                // Purchase Orders — can request purchases for their
                // team; no approve (that's Company Admin/Accountant's
                // finance-clearance job) and no edit/delete of others'
                // requests (PurchaseOrderController also blocks a
                // requester approving their own).
                'purchase_order.view', 'purchase_order.create',
            ],
            'accountant'          => [
                'invoice.view', 'invoice.create', 'invoice.edit', 'invoice.delete',
                'bill.view', 'bill.create', 'bill.edit', 'bill.delete',
                'expense.view', 'expense.create', 'expense.edit', 'expense.delete',
                'report.view',
                // No attendance.* — Accountant's menu doesn't include
                // Attendance; self check-in isn't part of their role.
                // Onboarding: stands in for "Finance" — completes only
                // their own payroll_setup checklist items.
                'onboarding.view', 'onboarding.edit',
                // Offboarding: final settlement & finance clearance only.
                'offboarding.view', 'offboarding.edit',
                // Purchase Orders — finance clearance/spend approval,
                // plus can request purchases (e.g. accounting supplies).
                // No .edit/.delete — Accountant approves, doesn't own
                // the record; no .create restriction on submitting
                // their own request though (create only, not edit).
                'purchase_order.view', 'purchase_order.create', 'purchase_order.approve',
            ],
            'hr'                  => [
                'employee.view', 'employee.create', 'employee.edit', 'employee.delete',
                'attendance.view', 'attendance.create', 'attendance.edit',
                'leave.view', 'leave.create', 'leave.edit', 'leave.approve',
                'payroll.view', 'payroll.create', 'payroll.edit',
                'report.view',
                // No meeting.view — HR's menu doesn't include Meetings.
                // Onboarding: HR is the primary owner of the whole
                // pipeline — full CRUD, unlike the other three roles
                // above who only get view + edit-their-own-tasks.
                'onboarding.view', 'onboarding.create', 'onboarding.edit', 'onboarding.delete',
                // Offboarding: HR is the primary owner here too — full
                // CRUD, including the overall exit status other roles
                // can't touch.
                'offboarding.view', 'offboarding.create', 'offboarding.edit', 'offboarding.delete',
            ],
            'compliance_officer'  => [
                'compliance.view', 'compliance.create', 'compliance.edit', 'compliance.delete',
                'document.view', 'document.create', 'document.edit', 'document.delete',
                'policy.view', 'policy.create', 'policy.edit', 'policy.delete',
                'report.view',
                // No attendance.* — Compliance Officer's menu doesn't
                // include Attendance.
            ],
            'employee'            => [
                'task.view', 'task.create', 'task.edit',
                // Read-only visibility into their own company's projects
                // (list + details) — no create/edit/delete, same pattern
                // as everywhere else Employee only gets a view right.
                'project.view',
                'meeting.view', 'meeting.create', 'meeting.edit',
                'attendance.view', 'attendance.create',
                'leave.view', 'leave.create',
                // Own payslips only — PayrollController::show() already
                // redirects Employee away from the org-wide run view to
                // myPayslips(), so payroll.view is safe to grant.
                'payroll.view',
                // Their own company's documents, read-only.
                'document.view',
                // Their own company's policies/manuals, read-only.
                'policy.view',
                // Offboarding: submit their own resignation (.create)
                // and complete only their own exit's employee-owned
                // checklist items (.edit) — OffboardingController/
                // OffboardingTaskController both additionally restrict
                // every action to the viewer's own record, so this
                // never exposes another employee's exit.
                'offboarding.view', 'offboarding.create', 'offboarding.edit',
                // Purchase Orders — can submit their own purchase
                // requests; no approve/edit/delete.
                'purchase_order.view', 'purchase_order.create',
            ],
        ];

        $allPermissions = $this->db->table('permissions')->select('id, slug')->get()->getResultArray();
        $slugToId       = array_column($allPermissions, 'id', 'slug');

        foreach ($map as $roleSlug => $permissionSlugs) {
            $role = $this->db->table('roles')->select('id')->where('slug', $roleSlug)->get()->getRowArray();

            if ($role === null) {
                continue;
            }

            $this->db->table('role_permissions')->where('role_id', $role['id'])->delete();

            $targetSlugs = $permissionSlugs === ['*'] ? array_keys($slugToId) : $permissionSlugs;

            $rows = [];
            foreach ($targetSlugs as $slug) {
                if (isset($slugToId[$slug])) {
                    $rows[] = ['role_id' => $role['id'], 'permission_id' => $slugToId[$slug]];
                }
            }

            if ($rows !== []) {
                $this->db->table('role_permissions')->insertBatch($rows);
            }
        }
        // super_admin intentionally gets no rows — PermissionFilter bypasses it by role slug.
    }
}
