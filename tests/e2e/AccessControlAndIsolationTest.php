<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-10  Cross-cutting: authentication, the role x module permission matrix, and company isolation of list screens.
 */
final class AccessControlAndIsolationTest extends E2ETestCase
{
    private array $w;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();
    }

    // ---------------------------------------------------------------- authentication

    public function testLoginLogoutAndRejectedCredentials(): void
    {
        $emp = $this->w['A']['employee'];

        $this->anonymous()->submit('/auth/attempt-login', ['email' => $emp['email'], 'password' => 'wrong-password']);
        $this->assertEmpty($_SESSION['isLoggedIn'] ?? null, 'wrong password');
        $this->assertFlash('error', 'Invalid email or password');

        // The message must not reveal whether the e-mail exists.
        $this->anonymous()->submit('/auth/attempt-login', ['email' => 'nobody@e2e.test', 'password' => 'whatever1']);
        $this->assertFlash('error', 'Invalid email or password');

        $this->anonymous()->submit('/auth/attempt-login', ['email' => $emp['email'], 'password' => self::PASSWORD]);
        $this->assertTrue($this->res->isRedirect());
        $this->assertTrue((bool) ($_SESSION['isLoggedIn'] ?? false));
        $this->assertSame('employee', $_SESSION['roleSlug']);
        $this->assertContains('task.view', $_SESSION['permissions']);
        $this->assertNotContains('payroll.edit', $_SESSION['permissions']);
        $this->assertNotNull($this->row('users', ['id' => $emp['user_id']])['last_login_at']);

        $this->actAs($emp)->visit('/auth/logout');
        $this->assertTrue($this->res->isRedirect());
        $this->assertStringContainsString('auth/login', $this->res->getRedirectUrl());
    }

    public function testInactiveAndDeletedUsersCannotLogIn(): void
    {
        $A = $this->w['A'];
        db_connect()->table('users')->where('id', $A['employee']['user_id'])->update(['status' => 'inactive']);
        $this->anonymous()->submit('/auth/attempt-login', ['email' => $A['employee']['email'], 'password' => self::PASSWORD]);
        $this->assertEmpty($_SESSION['isLoggedIn'] ?? null, 'deactivated user');

        db_connect()->table('users')->where('id', $A['hr']['user_id'])->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->anonymous()->submit('/auth/attempt-login', ['email' => $A['hr']['email'], 'password' => self::PASSWORD]);
        $this->assertEmpty($_SESSION['isLoggedIn'] ?? null, 'soft-deleted user');
    }

    public function testEveryProtectedAreaRedirectsAnonymousVisitorsToLogin(): void
    {
        foreach (['/hr/employees', '/hr/payroll', '/accounting/invoices', '/tasks', '/meetings', '/compliance', '/expenses', '/purchase-orders', '/documents', '/reports/financials', '/notifications/recent', '/'] as $uri) {
            $this->anonymous()->visit($uri);
            $this->assertTrue($this->res->isRedirect() || $this->res->response()->getStatusCode() === 401, "$uri must not be reachable anonymously");
            $this->assertStringContainsString('auth/login', $this->res->isRedirect() ? $this->res->getRedirectUrl() : 'auth/login');
        }
        foreach (['/hr/payroll/generate', '/accounting/invoices', '/hr/leave', '/tasks'] as $uri) {
            $this->anonymous()->submit($uri, ['x' => 1]);
            $this->assertTrue($this->res->isRedirect(), "POST $uri must not be reachable anonymously");
        }
    }

    public function testOnlySuperAdminManagesUsersAndRoles(): void
    {
        $A = $this->w['A'];
        foreach (['admin', 'hr', 'manager', 'accountant', 'compliance', 'employee'] as $role) {
            $this->actAs($A[$role])->visit('/auth/users');
            $this->assertDenied("$role must not manage users");
            $this->actAs($A[$role])->visit('/auth/roles');
            $this->assertDenied("$role must not manage roles");
        }
        $this->actAs($this->w['super'])->visit('/auth/users');
        $this->assertTrue($this->res->isOK());
    }

    public function testCompanySwitcherIsSuperAdminOnly(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];

        $this->actAs($A['admin'])->submit('/company-switch', ['company_id' => $B['company']['id']]);
        $this->assertArrayNotHasKey('active_company_id', $_SESSION, 'Company Admin cannot change company');
        $this->actAs($A['admin'])->visit('/hr/employees');
        $this->assertStringNotContainsString($B['employee']['name'], $this->body());

        // Super Admin sees everything by default, and one company once picked.
        $this->actAs($this->w['super'])->visit('/hr/employees');
        $this->assertStringContainsString($A['employee']['name'], $this->body());
        $this->assertStringContainsString($B['employee']['name'], $this->body());
        $this->actAs($this->w['super']);
        $this->as['active_company_id'] = $B['company']['id'];
        $this->visit('/hr/employees');
        $this->assertStringContainsString($B['employee']['name'], $this->body());
        $this->assertStringNotContainsString($A['employee']['name'], $this->body());
    }

    // ---------------------------------------------------------------- permission matrix

    public function testRoleByModulePermissionMatrixMatchesTheDatabase(): void
    {
        $A = $this->w['A'];
        $routes = [
            '/hr/employees' => 'employee.view', '/hr/attendance' => 'attendance.view', '/hr/leave' => 'leave.view', '/hr/payroll' => 'payroll.view',
            '/hr/onboarding' => 'onboarding.view', '/hr/offboarding' => 'offboarding.view', '/hr/performance' => 'performance.view',
            '/tasks' => 'task.view', '/projects' => 'project.view', '/meetings' => 'meeting.view', '/compliance' => 'compliance.view',
            '/accounting/invoices' => 'invoice.view', '/accounting/bills' => 'bill.view', '/expenses' => 'expense.view',
            '/purchase-orders' => 'purchase_order.view', '/vendors' => 'purchase_order.view', '/documents' => 'document.view',
            '/policies' => 'policy.view', '/reports/financials' => 'invoice.view', '/customers' => 'customer.view', '/companies' => 'company.view',
        ];

        $mismatch = [];
        foreach (['admin', 'hr', 'manager', 'accountant', 'compliance', 'employee'] as $role) {
            $granted = db_connect()->table('role_permissions rp')->select('p.slug')->join('permissions p', 'p.id = rp.permission_id')
                ->join('roles r', 'r.id = rp.role_id')->where('r.slug', $role === 'compliance' ? 'compliance_officer' : $role)->get()->getResultArray();
            $granted = array_column($granted, 'slug');

            foreach ($routes as $uri => $perm) {
                $this->actAs($A[$role])->visit($uri);
                $status  = $this->res->response()->getStatusCode();
                $allowed = $this->res->isOK();
                $denied  = $this->res->isRedirect() && stripos((string) $this->flash('error'), 'permission') !== false;

                if (in_array($perm, $granted, true) && ! $allowed) {
                    $mismatch[] = "$role should reach $uri ($perm) but got HTTP $status" . ($this->flash('error') ? ' / ' . $this->flash('error') : '');
                }
                if (! in_array($perm, $granted, true) && ! $denied) {
                    $mismatch[] = "$role must be denied $uri ($perm) but got HTTP $status";
                }
            }
        }

        $this->assertSame([], $mismatch, implode("\n", $mismatch));

        // Super Admin bypasses every permission check.
        foreach ($routes as $uri => $perm) {
            $this->actAs($this->w['super'])->visit($uri);
            $this->assertTrue($this->res->isOK(), "Super Admin should reach $uri (HTTP " . $this->res->response()->getStatusCode() . ')');
        }
    }

    public function testListScreensNeverLeakAnotherCompanysRecords(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];
        $db = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->table('vendors')->insert(['company_id' => $B['company']['id'], 'name' => 'BetaOnlyVendor', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('expenses')->insert(['company_id' => $B['company']['id'], 'vendor' => 'BetaOnlyExpense', 'category' => 'X', 'billing_cycle' => 'monthly', 'amount' => 5, 'status' => 'active', 'created_by' => $B['admin']['user_id'], 'created_at' => $now, 'updated_at' => $now]);
        $db->table('compliance_items')->insert(['company_id' => $B['company']['id'], 'compliance_type_id' => 1, 'title' => 'BetaOnlyCompliance', 'due_date' => date('Y-m-d', strtotime('+9 days')), 'recurrence' => 'none', 'status' => 'pending', 'reminder_days_before' => 7, 'created_by' => $B['admin']['user_id'], 'created_at' => $now, 'updated_at' => $now]);
        $db->table('projects')->insert(['company_id' => $B['company']['id'], 'name' => 'BetaOnlyProject', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('customers')->insert(['company_id' => $B['company']['id'], 'name' => 'BetaOnlyCustomer', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('meetings')->insert(['company_id' => $B['company']['id'], 'title' => 'BetaOnlyMeeting', 'meeting_date' => date('Y-m-d'), 'join_token' => bin2hex(random_bytes(20)), 'created_by' => $B['admin']['user_id'], 'created_at' => $now, 'updated_at' => $now]);

        $checks = ['/vendors' => 'BetaOnlyVendor', '/expenses' => 'BetaOnlyExpense', '/compliance' => 'BetaOnlyCompliance', '/projects' => 'BetaOnlyProject', '/customers' => 'BetaOnlyCustomer', '/meetings' => 'BetaOnlyMeeting'];
        foreach ($checks as $uri => $needle) {
            $this->actAs($A['admin'])->visit($uri);
            $this->assertTrue($this->res->isOK(), $uri . ' HTTP ' . $this->res->response()->getStatusCode());
            $this->assertStringNotContainsString($needle, $this->body(), "company A must not see $needle on $uri");

            $this->actAs($B['admin'])->visit($uri);
            $this->assertStringContainsString($needle, $this->body(), "company B sees its own record on $uri");
        }
    }

    public function testAdminWithoutAnEmployeeProfileSeesNoCompanyData(): void
    {
        $A   = $this->w['A'];
        $now = date('Y-m-d H:i:s');
        $db  = db_connect();
        $db->table('invoices')->insert([
            'company_id' => $A['company']['id'], 'invoice_number' => 'ALP-SECRET-1', 'customer_name' => 'SecretCustomer', 'customer_country_id' => $A['company']['country_id'],
            'amount' => 10, 'subtotal' => 10, 'gst_type' => 'intra_state', 'issue_date' => date('Y-m-d'), 'status' => 'sent', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('projects')->insert(['company_id' => $A['company']['id'], 'name' => 'SecretProject', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('campaigns')->insert(['company_id' => $A['company']['id'], 'name' => 'SecretCampaign', 'channel' => 'email', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('websites')->insert(['company_id' => $A['company']['id'], 'domain' => 'secretsite.test', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

        $db->table('vendors')->insert(['company_id' => $A['company']['id'], 'name' => 'SecretVendor', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $db->table('expenses')->insert(['company_id' => $A['company']['id'], 'vendor' => 'SecretExpense', 'category' => 'X', 'billing_cycle' => 'monthly', 'amount' => 5, 'status' => 'active', 'created_by' => $A['admin']['user_id'], 'created_at' => $now, 'updated_at' => $now]);
        $db->table('compliance_items')->insert(['company_id' => $A['company']['id'], 'compliance_type_id' => 1, 'title' => 'SecretCompliance', 'due_date' => date('Y-m-d', strtotime('+9 days')), 'recurrence' => 'none', 'status' => 'pending', 'reminder_days_before' => 7, 'created_by' => $A['admin']['user_id'], 'created_at' => $now, 'updated_at' => $now]);
        $db->table('customers')->insert(['company_id' => $A['company']['id'], 'name' => 'SecretCustomerRow', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

        $orphanAdmin = $this->person(null, 'admin', 'Orphan Admin');   // a Company Admin login with no employee profile yet
        foreach (['/accounting/invoices' => 'SecretCustomer', '/projects' => 'SecretProject', '/campaigns' => 'SecretCampaign', '/websites' => 'secretsite.test', '/vendors' => 'SecretVendor', '/expenses' => 'SecretExpense', '/compliance' => 'SecretCompliance', '/customers' => 'SecretCustomerRow', '/hr/employees' => $A['employee']['name'], '/tasks' => 'Secret'] as $uri => $needle) {
            $this->actAs($orphanAdmin)->visit($uri);
            $this->assertStringNotContainsString($needle, $this->body(), "an unprovisioned admin must see no company data on $uri");
        }
    }
}
