<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-01 (first half)  Employee record -> attendance -> leave request -> approval/rejection/cancellation.
 * Payroll, payslip and exit are in PayrollPayslipFlowTest / OffboardingFlowTest.
 */
final class EmployeeAttendanceLeaveFlowTest extends E2ETestCase
{
    private array $w;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();
    }

    private function leaveTypeId(string $name): int
    {
        return (int) db_connect()->table('leave_types')->where('name', $name)->get()->getRow('id');
    }

    private function apply(array $as, string $type, string $from, string $to, string $reason = 'Family function'): ?array
    {
        $before = $this->countRows('leave_requests');
        $this->actAs($as)->submit('/hr/leave', ['leave_type_id' => $this->leaveTypeId($type), 'start_date' => $from, 'end_date' => $to, 'reason' => $reason]);

        if ($this->countRows('leave_requests') <= $before) {
            return null;
        }

        return db_connect()->table('leave_requests')->where('user_id', $as['user_id'])->orderBy('id', 'DESC')->get()->getRowArray();
    }

    // ---------------------------------------------------------------- employee record

    public function testHrCreatesEmployeeProfileForAnExistingLogin(): void
    {
        $A    = $this->w['A'];
        $user = $this->person(null, 'employee', 'New Joiner');   // a login with no profile yet

        $this->actAs($A['hr'])->submit('/hr/employees', [
            'user_id' => $user['user_id'], 'company_id' => $A['company']['id'], 'department_id' => $this->w['dept'], 'employee_code' => 'ALP900',
            'designation' => 'Analyst', 'reporting_manager_id' => $A['manager']['user_id'], 'employment_type' => 'full_time', 'status' => 'active',
            'date_of_joining' => date('Y-m-01'),
        ]);

        $profile = $this->row('employee_profiles', ['user_id' => $user['user_id']]);
        $this->assertNotNull($profile);
        $this->assertSame('ALP900', $profile['employee_code']);
        $this->assertSame((string) $A['company']['id'], (string) $profile['company_id']);
        $this->assertNotNull($this->row('activity_logs', ['module' => 'employee', 'action' => 'create', 'record_id' => $profile['id']]));

        // The same login cannot get a second profile, and the code cannot be reused (duplicate submission).
        $this->actAs($A['hr'])->submit('/hr/employees', [
            'user_id' => $user['user_id'], 'company_id' => $A['company']['id'], 'employee_code' => 'ALP901', 'employment_type' => 'full_time', 'status' => 'active',
        ]);
        $this->assertSame(1, $this->countRows('employee_profiles', ['user_id' => $user['user_id']]));

        $other = $this->person(null, 'employee', 'Second Joiner');
        $this->actAs($A['hr'])->submit('/hr/employees', [
            'user_id' => $other['user_id'], 'company_id' => $A['company']['id'], 'employee_code' => 'ALP900', 'employment_type' => 'full_time', 'status' => 'active',
        ]);
        $this->assertSame(0, $this->countRows('employee_profiles', ['user_id' => $other['user_id']]), 'duplicate employee code is refused');
    }

    public function testEmployeeCreationValidationAndScope(): void
    {
        $A    = $this->w['A'];
        $B    = $this->w['B'];
        $user = $this->person(null, 'employee', 'Scope Test');
        $base = ['user_id' => $user['user_id'], 'employee_code' => 'ALP910', 'employment_type' => 'full_time', 'status' => 'active'];

        // HR of A may not place an employee in company B.
        $this->actAs($A['hr'])->submit('/hr/employees', $base + ['company_id' => $B['company']['id']]);
        $this->assertSame(0, $this->countRows('employee_profiles', ['user_id' => $user['user_id']]));
        $this->assertFlash('error', 'own company');

        // Future date of birth.
        $this->actAs($A['hr'])->submit('/hr/employees', $base + ['company_id' => $A['company']['id'], 'date_of_birth' => date('Y-m-d', strtotime('+1 day'))]);
        $this->assertSame(0, $this->countRows('employee_profiles', ['user_id' => $user['user_id']]));

        // Roles that do not hold employee.create are stopped by the permission filter.
        foreach (['employee', 'manager', 'accountant', 'compliance'] as $role) {
            $this->actAs($A[$role])->submit('/hr/employees', $base + ['company_id' => $A['company']['id']]);
            $this->assertSame(0, $this->countRows('employee_profiles', ['user_id' => $user['user_id']]), "$role must not create employees");
        }

        // Company Admin may.
        $this->actAs($A['admin'])->submit('/hr/employees', $base + ['company_id' => $A['company']['id']]);
        $this->assertSame(1, $this->countRows('employee_profiles', ['user_id' => $user['user_id']]));
    }

    public function testReportingManagerMustBelongToTheSameCompany(): void
    {
        $A    = $this->w['A'];
        $B    = $this->w['B'];
        $user = $this->person(null, 'employee', 'Cross Manager');

        $this->actAs($A['hr'])->submit('/hr/employees', [
            'user_id' => $user['user_id'], 'company_id' => $A['company']['id'], 'employee_code' => 'ALP920', 'employment_type' => 'full_time', 'status' => 'active',
            'reporting_manager_id' => $B['manager']['user_id'],
        ]);
        $profile = $this->row('employee_profiles', ['user_id' => $user['user_id']]);

        $this->assertRuleOrKnownDefect(
            $profile === null || (string) $profile['reporting_manager_id'] !== (string) $B['manager']['user_id'],
            'D-06',
            'An employee can be given a reporting manager from ANOTHER company (EmployeeController::store/update do not check it). Leave/onboarding/offboarding notifications and leave approval rights then cross the company boundary.'
        );
    }

    public function testEmployeeListIsCompanyScoped(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];

        $this->actAs($A['hr'])->visit('/hr/employees');
        $body = $this->body();
        $this->assertStringContainsString($A['employee']['name'], $body);
        $this->assertStringNotContainsString($B['employee']['name'], $body);

        $this->actAs($A['hr'])->visit('/hr/employees/' . $B['employee']['profile_id']);
        $this->assertTrue($this->res->isRedirect(), "other company's profile is not reachable");
        $this->assertFlash('error', 'not found');

        $this->actAs($this->w['super'])->visit('/hr/employees');
        $this->assertStringContainsString($B['employee']['name'], $this->body(), 'Super Admin sees all companies');
    }

    // ---------------------------------------------------------------- attendance

    public function testSelfServiceAttendanceCheckInAndOut(): void
    {
        $emp = $this->w['A']['employee'];

        $this->actAs($emp)->submit('/hr/attendance/check-out');
        $this->assertFlash('error', 'Check in before checking out');

        $this->actAs($emp)->submit('/hr/attendance/check-in');
        $row = $this->row('attendance', ['user_id' => $emp['user_id'], 'date' => date('Y-m-d')]);
        $this->assertSame('present', $row['status']);
        $this->assertNotNull($row['check_in']);

        $this->actAs($emp)->submit('/hr/attendance/check-in');   // double click
        $this->assertFlash('error', 'already checked in');
        $this->assertSame(1, $this->countRows('attendance', ['user_id' => $emp['user_id']]));

        $this->actAs($emp)->submit('/hr/attendance/check-out');
        $this->assertNotNull($this->row('attendance', ['user_id' => $emp['user_id']])['check_out']);
        $this->actAs($emp)->submit('/hr/attendance/check-out');
        $this->assertFlash('error', 'already checked out');

        // Super Admin has no self-service attendance.
        $this->actAs($this->w['super'])->submit('/hr/attendance/check-in');
        $this->assertFlash('error', 'Super Admin');
    }

    public function testHrMarksAttendanceWithinOwnCompanyOnly(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];
        $d = date('Y-m-01');

        $this->actAs($A['hr'])->submit('/hr/attendance/mark', ['user_id' => $A['employee']['user_id'], 'date' => $d, 'status' => 'absent']);
        $this->assertSame('absent', $this->row('attendance', ['user_id' => $A['employee']['user_id'], 'date' => $d])['status']);

        // Correcting the same day updates the row, it does not add a second one.
        $this->actAs($A['hr'])->submit('/hr/attendance/mark', ['user_id' => $A['employee']['user_id'], 'date' => $d, 'status' => 'present', 'check_in' => '09:15']);
        $this->assertSame(1, $this->countRows('attendance', ['user_id' => $A['employee']['user_id'], 'date' => $d]));
        $this->assertSame('present', $this->row('attendance', ['user_id' => $A['employee']['user_id'], 'date' => $d])['status']);

        // Another company's employee, and invalid statuses, are refused.
        $this->actAs($A['hr'])->submit('/hr/attendance/mark', ['user_id' => $B['employee']['user_id'], 'date' => $d, 'status' => 'absent']);
        $this->assertSame(0, $this->countRows('attendance', ['user_id' => $B['employee']['user_id']]));
        $this->actAs($A['hr'])->submit('/hr/attendance/mark', ['user_id' => $A['employee']['user_id'], 'date' => $d, 'status' => 'sleeping']);
        $this->assertFlash('error', 'valid date and status');

        // An employee cannot mark attendance for others.
        $this->actAs($A['employee'])->submit('/hr/attendance/mark', ['user_id' => $A['manager']['user_id'], 'date' => $d, 'status' => 'absent']);
        $this->assertSame(0, $this->countRows('attendance', ['user_id' => $A['manager']['user_id']]));
    }

    // ---------------------------------------------------------------- leave

    public function testLeaveRequestIsApprovedByHr(): void
    {
        $A     = $this->w['A'];
        $emp   = $A['employee'];
        $from  = date('Y-m-d', strtotime('+10 days'));
        $to    = date('Y-m-d', strtotime('+11 days'));

        $req = $this->apply($emp, 'Sick Leave', $from, $to);
        $this->assertNotNull($req, 'leave request is created: ' . json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertSame('pending', $req['status']);
        $this->assertEquals(2.0, (float) $req['days']);
        $this->assertCount(1, $this->notificationsFor($A['manager']['user_id'], 'leave_requested'), 'reporting manager is told');

        // Nobody may decide their own request.
        $this->actAs($emp)->submit("/hr/leave/{$req['id']}/approve");
        $this->assertSame('pending', $this->row('leave_requests', ['id' => $req['id']])['status']);

        // Another company's HR cannot decide it.
        $this->actAs($this->w['B']['hr'])->submit("/hr/leave/{$req['id']}/approve");
        $this->assertSame('pending', $this->row('leave_requests', ['id' => $req['id']])['status'], 'cross-company approval is refused');

        // O-01: the reporting manager is notified, but the Manager role holds no leave.* permission by design
        // (RolePermissionSeeder), so the notification leads nowhere - the decision has to come from HR / Company Admin.
        $this->actAs($A['manager'])->submit("/hr/leave/{$req['id']}/approve");
        $this->assertDenied('Manager has no leave.approve');
        $this->assertSame('pending', $this->row('leave_requests', ['id' => $req['id']])['status']);

        // HR approves.
        $this->actAs($A['hr'])->submit("/hr/leave/{$req['id']}/approve");
        $row = $this->row('leave_requests', ['id' => $req['id']]);
        $this->assertSame('approved', $row['status']);
        $this->assertSame((string) $A['hr']['user_id'], (string) $row['approved_by']);
        $this->assertNotNull($row['approved_at']);
        $this->assertCount(1, $this->notificationsFor($emp['user_id'], 'leave_approved'));

        // Deciding twice (double click / second approver) changes nothing and sends no second notification.
        $this->actAs($A['admin'])->submit("/hr/leave/{$req['id']}/reject");
        $this->assertSame('approved', $this->row('leave_requests', ['id' => $req['id']])['status']);
        $this->assertFlash('error', 'already been actioned');
        $this->assertCount(1, $this->notificationsFor($emp['user_id']), 'exactly one decision notification');

        // An approved request can no longer be cancelled by the employee.
        $this->actAs($emp)->submit("/hr/leave/{$req['id']}/cancel");
        $this->assertSame('approved', $this->row('leave_requests', ['id' => $req['id']])['status']);
    }

    public function testLeaveRejectedAndCancelledPaths(): void
    {
        $A   = $this->w['A'];
        $emp = $A['employee'];

        $r1 = $this->apply($emp, 'Casual Leave', date('Y-m-d', strtotime('+20 days')), date('Y-m-d', strtotime('+20 days')));
        $this->actAs($A['hr'])->submit("/hr/leave/{$r1['id']}/reject");
        $this->assertSame('rejected', $this->row('leave_requests', ['id' => $r1['id']])['status']);
        $this->assertCount(1, $this->notificationsFor($emp['user_id'], 'leave_rejected'));

        $this->actAs($emp)->submit("/hr/leave/{$r1['id']}/cancel");
        $this->assertSame('rejected', $this->row('leave_requests', ['id' => $r1['id']])['status'], 'a decided request cannot be cancelled');

        $r2 = $this->apply($emp, 'Casual Leave', date('Y-m-d', strtotime('+30 days')), date('Y-m-d', strtotime('+31 days')));
        $this->actAs($A['hr'])->submit("/hr/leave/{$r2['id']}/cancel");   // not the owner
        $this->assertSame('pending', $this->row('leave_requests', ['id' => $r2['id']])['status']);
        $this->actAs($emp)->submit("/hr/leave/{$r2['id']}/cancel");
        $this->assertSame('cancelled', $this->row('leave_requests', ['id' => $r2['id']])['status']);
        $this->actAs($A['hr'])->submit("/hr/leave/{$r2['id']}/approve");
        $this->assertSame('cancelled', $this->row('leave_requests', ['id' => $r2['id']])['status'], 'cancelled request cannot be approved');
    }

    public function testLeaveInputValidation(): void
    {
        $emp = $this->w['A']['employee'];

        $this->assertNull($this->apply($emp, 'Casual Leave', '2026-12-10', '2026-12-05'), 'end before start');
        $this->assertFlash('error', 'before the start date');

        $crashed = false;
        try {
            $this->actAs($emp)->submit('/hr/leave', ['leave_type_id' => 9999, 'start_date' => '2026-12-05', 'end_date' => '2026-12-06']);
        } catch (\Throwable $e) {
            $crashed = true;   // unhandled foreign-key exception => HTTP 500
        }
        $this->assertSame(0, $this->countRows('leave_requests'), 'unknown leave type is never stored');
        $this->assertRuleOrKnownDefect(! $crashed, 'D-11', 'A non-existent leave_type_id is not validated: the request ends in an unhandled foreign-key exception (HTTP 500) instead of a validation message.');

        $this->actAs($this->w['super'])->submit('/hr/leave', ['leave_type_id' => 1, 'start_date' => '2026-12-05', 'end_date' => '2026-12-06']);
        $this->assertSame(0, $this->countRows('leave_requests'), 'Super Admin does not apply for leave');
    }

    public function testApprovalHierarchyAndPermissionFilter(): void
    {
        $A = $this->w['A'];

        // Managers hold no leave permissions at all: they cannot apply (route filter).
        $this->actAs($A['manager'])->submit('/hr/leave', ['leave_type_id' => 1, 'start_date' => date('Y-m-d', strtotime('+5 days')), 'end_date' => date('Y-m-d', strtotime('+5 days'))]);
        $this->assertSame(0, $this->countRows('leave_requests'));

        // HR's own leave is approved by Company Admin (higher rank); HR cannot approve it themselves.
        $hrReq = $this->apply($A['hr'], 'Casual Leave', date('Y-m-d', strtotime('+5 days')), date('Y-m-d', strtotime('+5 days')));
        $this->actAs($A['hr'])->submit("/hr/leave/{$hrReq['id']}/approve");
        $this->assertSame('pending', $this->row('leave_requests', ['id' => $hrReq['id']])['status'], 'no self approval');
        $this->actAs($A['admin'])->submit("/hr/leave/{$hrReq['id']}/approve");
        $this->assertSame('approved', $this->row('leave_requests', ['id' => $hrReq['id']])['status'], 'Company Admin outranks HR');

        $adminReq = $this->apply($A['admin'], 'Casual Leave', date('Y-m-d', strtotime('+7 days')), date('Y-m-d', strtotime('+7 days')));
        $this->actAs($A['hr'])->submit("/hr/leave/{$adminReq['id']}/approve");
        $this->assertSame('pending', $this->row('leave_requests', ['id' => $adminReq['id']])['status'], 'HR cannot approve their superior');
        $this->actAs($this->w['super'])->submit("/hr/leave/{$adminReq['id']}/approve");
        $this->assertSame('approved', $this->row('leave_requests', ['id' => $adminReq['id']])['status'], 'Super Admin is the top of the chain');

        // Roles without leave.approve never reach the controller.
        $eReq = $this->apply($A['employee'], 'Casual Leave', date('Y-m-d', strtotime('+6 days')), date('Y-m-d', strtotime('+6 days')));
        foreach (['accountant', 'compliance'] as $role) {
            $this->actAs($A[$role])->submit("/hr/leave/{$eReq['id']}/approve");
            $this->assertDenied("$role has no leave.approve");
            $this->assertSame('pending', $this->row('leave_requests', ['id' => $eReq['id']])['status']);
        }
    }

    public function testLeaveBusinessRulesThatAreNotEnforced(): void
    {
        $emp  = $this->w['A']['employee'];
        $from = date('Y-m-d', strtotime('+40 days'));
        $to   = date('Y-m-d', strtotime('+41 days'));

        // D-07: the same dates can be requested twice (overlap / duplicate submission).
        $first  = $this->apply($emp, 'Casual Leave', $from, $to);
        $second = $this->apply($emp, 'Casual Leave', $from, $to);
        $this->assertNotNull($first);
        $this->assertRuleOrKnownDefect(
            $this->countRows('leave_requests', ['user_id' => $emp['user_id']]) === 1,
            'D-07',
            'An employee can submit overlapping/identical leave requests (a double click files two requests for the same days); both can then be approved.'
        );
    }

    public function testLeaveQuotaIsChecked(): void
    {
        $emp = $this->w['A']['employee'];   // Casual Leave quota is 12 days a year

        $req = $this->apply($emp, 'Casual Leave', date('Y-m-d', strtotime('+50 days')), date('Y-m-d', strtotime('+79 days')));   // 30 days
        $this->assertRuleOrKnownDefect(
            $req === null,
            'D-08',
            'Leave requests are not checked against the leave type quota/balance: 30 days of Casual Leave (quota 12) is accepted and can be approved.'
        );
    }

    public function testApprovedLeaveShowsOnAttendance(): void
    {
        $A    = $this->w['A'];
        $day  = date('Y-m-d', strtotime('+3 days'));
        $req  = $this->apply($A['employee'], 'Sick Leave', $day, $day);
        $this->actAs($A['hr'])->submit("/hr/leave/{$req['id']}/approve");

        $att = $this->row('attendance', ['user_id' => $A['employee']['user_id'], 'date' => $day]);
        $this->assertRuleOrKnownDefect(
            $att !== null && $att['status'] === 'on_leave',
            'D-09',
            'Approving leave does not create an "on_leave" attendance row, so attendance reports/KPIs and absence counts ignore approved leave (payroll LOP is computed from attendance "absent" plus unpaid-leave requests separately).'
        );
    }
}
