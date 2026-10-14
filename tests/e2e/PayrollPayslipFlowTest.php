<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-01 (middle)  Salary structure -> attendance/leave feed -> payroll run -> payslips -> notification
 * -> payslip document (company template) -> mark paid.
 */
final class PayrollPayslipFlowTest extends E2ETestCase
{
    private array $w;
    private int $month;
    private int $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w     = $this->world();
        $this->month = (int) date('n');
        $this->year  = (int) date('Y');
    }

    private function setStructure(array $hr, array $person, array $over = []): void
    {
        $this->actAs($hr)->submit('/hr/payroll/structure/' . $person['user_id'], $over + [
            'basic' => '30000', 'hra' => '12000', 'allowances' => '3000', 'deductions' => '2000', 'effective_from' => date('Y-m-01'),
        ]);
    }

    private function generate(array $by, ?int $month = null, ?int $year = null): void
    {
        $this->actAs($by)->submit('/hr/payroll/generate', ['month' => $month ?? $this->month, 'year' => $year ?? $this->year]);
    }

    public function testSalaryStructureIsMaintainedByAuthorisedRolesWithinTheCompany(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];

        $this->setStructure($A['hr'], $A['employee']);
        $s = $this->row('salary_structures', ['user_id' => $A['employee']['user_id']]);
        $this->assertNotNull($s);
        $this->assertEquals(30000, $s['basic']);

        // Saving again updates the single row (no duplicates).
        $this->setStructure($A['hr'], $A['employee'], ['basic' => '31000']);
        $this->assertSame(1, $this->countRows('salary_structures', ['user_id' => $A['employee']['user_id']]));
        $this->assertEquals(31000, $this->row('salary_structures', ['user_id' => $A['employee']['user_id']])['basic']);

        // Invalid / foreign / unauthorised writes change nothing.
        $this->setStructure($A['hr'], $A['employee'], ['basic' => '-5']);
        $this->assertEquals(31000, $this->row('salary_structures', ['user_id' => $A['employee']['user_id']])['basic'], 'negative basic rejected');
        $this->setStructure($B['hr'], $A['employee'], ['basic' => '1']);
        $this->assertEquals(31000, $this->row('salary_structures', ['user_id' => $A['employee']['user_id']])['basic'], 'other company HR refused');
        foreach (['employee', 'manager', 'accountant', 'compliance'] as $role) {
            $this->setStructure($A[$role], $A['employee'], ['basic' => '2']);
            $this->assertEquals(31000, $this->row('salary_structures', ['user_id' => $A['employee']['user_id']])['basic'], "$role cannot edit salaries");
        }
    }

    public function testPayrollRunComputesLossOfPayAndCreatesPayslips(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];
        $this->setStructure($A['hr'], $A['employee']);          // gross 45,000 / deductions 2,000
        $this->setStructure($A['hr'], $A['manager'], ['basic' => '50000', 'hra' => '0', 'allowances' => '0', 'deductions' => '0']);
        $this->salary($B['employee']);                           // company B staff must not appear in A's run
        $noSalary = $A['compliance'];                            // no structure -> not paid

        // Inputs: one absent day + 2 days of approved UNPAID leave in the month -> 3 LOP days.
        $this->actAs($A['hr'])->submit('/hr/attendance/mark', ['user_id' => $A['employee']['user_id'], 'date' => date('Y-m-03'), 'status' => 'absent']);
        $this->actAs($A['employee'])->submit('/hr/leave', [
            'leave_type_id' => (int) db_connect()->table('leave_types')->where('name', 'Unpaid Leave')->get()->getRow('id'),
            'start_date' => date('Y-m-07'), 'end_date' => date('Y-m-08'), 'reason' => 'Personal',
        ]);
        $leave = $this->row('leave_requests', ['user_id' => $A['employee']['user_id']]);
        $this->actAs($A['hr'])->submit("/hr/leave/{$leave['id']}/approve");
        // A paid-leave day must NOT count as LOP.
        $this->actAs($A['employee'])->submit('/hr/leave', [
            'leave_type_id' => (int) db_connect()->table('leave_types')->where('name', 'Casual Leave')->get()->getRow('id'),
            'start_date' => date('Y-m-10'), 'end_date' => date('Y-m-10'), 'reason' => 'Paid',
        ]);
        $paidLeave = db_connect()->table('leave_requests')->where('user_id', $A['employee']['user_id'])->orderBy('id', 'DESC')->get()->getRowArray();
        $this->actAs($A['hr'])->submit("/hr/leave/{$paidLeave['id']}/approve");

        $this->generate($A['hr']);

        $run = $this->row('payroll_runs', ['month' => $this->month, 'year' => $this->year]);
        $this->assertNotNull($run, 'a run was created: ' . json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertSame('processed', $run['status']);
        $this->assertSame((string) $A['hr']['user_id'], (string) $run['created_by']);

        $slips = $this->rows('payslips', ['payroll_run_id' => $run['id']]);
        $this->assertCount(2, $slips, 'only company A staff that have a salary structure');
        $this->assertSame(0, $this->countRows('payslips', ['user_id' => $B['employee']['user_id']]), 'company B is not paid by company A HR');
        $this->assertSame(0, $this->countRows('payslips', ['user_id' => $noSalary['user_id']]));

        $days = (int) date('t');
        $slip = $this->row('payslips', ['payroll_run_id' => $run['id'], 'user_id' => $A['employee']['user_id']]);
        $lop  = round(45000 / $days * 3, 2);
        $this->assertEquals(3.0, (float) $slip['lop_days'], 'absent day + 2 unpaid leave days; paid leave excluded');
        $this->assertEquals($lop, (float) $slip['lop_amount']);
        $this->assertEquals(45000, (float) $slip['gross']);
        $this->assertEquals(round(45000 - 2000 - $lop, 2), (float) $slip['net']);

        $clean = $this->row('payslips', ['payroll_run_id' => $run['id'], 'user_id' => $A['manager']['user_id']]);
        $this->assertEquals(50000, (float) $clean['net'], 'no LOP, no deductions');

        // Employees are told their payslip is ready, once.
        $this->assertCount(1, $this->notificationsFor($A['employee']['user_id'], 'payroll_processed'));
        $this->assertNotNull($this->row('activity_logs', ['module' => 'payroll', 'action' => 'create', 'record_id' => $run['id']]));

        // The same period cannot be processed twice (duplicate click / retry): still one run, same payslips, no new notifications.
        $this->generate($A['hr']);
        $this->assertFlash('error', 'already exists');
        $this->assertSame(1, $this->countRows('payroll_runs'));
        $this->assertSame(2, $this->countRows('payslips'));
        $this->assertCount(1, $this->notificationsFor($A['employee']['user_id'], 'payroll_processed'));
    }

    public function testInactiveEmployeesAreNotPaidAndEmptyRunsAreRefused(): void
    {
        $A = $this->w['A'];

        $this->generate($A['hr']);
        $this->assertFlash('error', 'No employees have a salary structure');
        $this->assertSame(0, $this->countRows('payroll_runs'), 'no empty run is left behind');

        $this->salary($A['employee']);
        db_connect()->table('employee_profiles')->where('id', $A['employee']['profile_id'])->update(['status' => 'resigned']);
        $this->generate($A['hr']);
        $this->assertSame(0, $this->countRows('payroll_runs'), 'a resigned employee is not payable');
    }

    public function testPayrollInputValidationAndPermissions(): void
    {
        $A = $this->w['A'];
        $this->salary($A['employee']);

        foreach ([[13, 2026], [0, 2026], [5, 1999], [5, 2101]] as [$m, $y]) {
            $this->generate($A['hr'], $m, $y);
            $this->assertSame(0, $this->countRows('payroll_runs'), "month $m / year $y rejected");
        }

        foreach (['employee', 'manager', 'accountant', 'compliance'] as $role) {
            $this->generate($A[$role]);
            $this->assertSame(0, $this->countRows('payroll_runs'), "$role cannot run payroll");
        }
        $this->anonymous()->submit('/hr/payroll/generate', ['month' => 1, 'year' => 2026]);
        $this->assertSame(0, $this->countRows('payroll_runs'));

        $this->generate($A['admin']);   // Company Admin holds payroll.create
        $this->assertSame(1, $this->countRows('payroll_runs'));
    }

    public function testPayslipVisibilityAndDocument(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];
        $this->salary($A['employee']);
        $this->salary($A['manager']);
        $this->generate($A['hr']);
        $mine   = $this->row('payslips', ['user_id' => $A['employee']['user_id']]);
        $theirs = $this->row('payslips', ['user_id' => $A['manager']['user_id']]);

        // Visibility: own payslip yes; colleague's no; other company's HR no; same-company HR yes.
        $this->actAs($A['employee'])->visit('/hr/payroll/my-payslips');
        $this->assertTrue($this->res->isOK());
        $this->actAs($A['employee'])->visit('/hr/payroll/payslip/' . $theirs['id']);
        $this->assertFlash('error', 'permission to view this payslip');
        $this->actAs($B['hr'])->visit('/hr/payroll/payslip/' . $mine['id']);
        $this->assertFlash('error', 'permission to view this payslip');
        $this->actAs($A['employee'])->visit('/hr/payroll/' . $mine['payroll_run_id']);
        $this->assertTrue($this->res->isRedirect(), 'employees never see the whole run');

        // Official payslips are template-only: no active template -> refused with a clear message, nothing archived.
        $this->actAs($A['employee'])->visit('/hr/payroll/payslip/' . $mine['id'] . '/pdf');
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'No active Payslip template');
        $this->assertSame(0, $this->countRows('generated_documents'));

        // A template belonging to ANOTHER company is never used.
        $this->template($B['company'], 'payslip', '<p>{{employee_name}} {{period}} {{net_pay}}</p>');
        $this->actAs($A['employee'])->visit('/hr/payroll/payslip/' . $mine['id'] . '/pdf');
        $this->assertFlash('error', 'No active Payslip template');

        // With the company's own template the payslip PDF is produced and archived exactly once.
        $tpl = $this->template($A['company'], 'payslip', '<html><body><h1>Payslip</h1><p>{{employee_name}} - {{period}} - Net {{net_pay}}</p></body></html>');
        $this->actAs($A['employee']);
        $this->downloadArchived('/hr/payroll/payslip/' . $mine['id'] . '/pdf', 'payslip', (int) $mine['id']);
        $this->assertTrue($this->res->isOK(), 'HTTP ' . $this->res->response()->getStatusCode() . ' ' . json_encode($this->flash('error')));
        $this->assertStringStartsWith('%PDF', $this->body());
        $docs = $this->rows('generated_documents', ['document_type' => 'payslip']);
        $this->assertCount(1, $docs);
        $this->assertSame((string) $A['company']['id'], (string) $docs[0]['company_id']);
        $this->assertSame((string) $tpl, (string) $docs[0]['template_id']);
        $this->assertSame((string) $mine['id'], (string) $docs[0]['record_id']);
        $this->assertStringContainsString($A['employee']['name'], (string) $docs[0]['data_snapshot']);

        $this->actAs($A['employee'])->visit('/hr/payroll/payslip/' . $mine['id'] . '/pdf');   // download again
        $this->assertCount(1, $this->rows('generated_documents', ['document_type' => 'payslip']), 'unchanged data re-serves the archived file, no duplicate');

        // Activating a newer template version does not rewrite the already issued payslip.
        $this->template($A['company'], 'payslip', '<p>NEW LAYOUT {{employee_name}} {{period}} {{net_pay}}</p>', 'inactive', 2);
        $this->assertSame(1, $this->countRows('generated_documents', ['document_type' => 'payslip']));
    }

    public function testMarkPaidAndLifecycleOfARun(): void
    {
        $A = $this->w['A'];
        $this->salary($A['employee']);
        $this->generate($A['hr']);
        $run = $this->row('payroll_runs', ['month' => $this->month, 'year' => $this->year]);

        $this->actAs($A['employee'])->submit("/hr/payroll/{$run['id']}/mark-paid");
        $this->assertSame('processed', $this->row('payroll_runs', ['id' => $run['id']])['status'], 'employees cannot mark a run paid');

        $this->actAs($A['hr'])->submit("/hr/payroll/{$run['id']}/mark-paid");
        $this->assertSame('paid', $this->row('payroll_runs', ['id' => $run['id']])['status']);

        // Paying the run books the net salaries in Accounting: ONE cash-out payment for the company, linked to the run.
        $expectedNet = (float) db_connect()->table('payslips')->selectSum('net')->where('payroll_run_id', $run['id'])->get()->getRow('net');
        $pay = $this->rows('payments', ['payroll_run_id' => $run['id']]);
        $this->assertCount(1, $pay);
        $this->assertSame('out', $pay[0]['direction']);
        $this->assertEquals($expectedNet, (float) $pay[0]['amount']);
        $this->assertSame((string) $A['company']['id'], (string) $pay[0]['company_id']);
        $this->assertSame('payroll', $pay[0]['method']);
        $this->assertNull($pay[0]['invoice_id']);
        $this->assertNull($pay[0]['bill_id']);
        $this->assertNotNull($this->row('payroll_runs', ['id' => $run['id']])['paid_at']);

        // Clicking again is a no-op: still one payment, nothing re-booked.
        $this->actAs($A['hr'])->submit("/hr/payroll/{$run['id']}/mark-paid");
        $this->assertSame(1, $this->countRows('payments', ['payroll_run_id' => $run['id']]));
        $this->assertFlash('success', 'already');

        // The salary payment shows in the cash-out report and cannot be removed from the invoice/bill screens.
        $this->actAs($A['accountant'])->submit('/accounting/payments/' . $pay[0]['id'] . '/delete');
        $this->assertSame(1, $this->countRows('payments', ['payroll_run_id' => $run['id']]));
        $this->assertSame(0, $this->countRows('expenses'));

        // A paid period cannot be re-generated.
        $this->generate($A['hr']);
        $this->assertSame(1, $this->countRows('payroll_runs'));
    }

    public function testPayrollIsIsolatedBetweenCompanies(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];
        $this->salary($A['employee']);
        $this->salary($B['employee']);

        $this->generate($A['hr']);
        $this->assertSame(1, $this->countRows('payroll_runs'));

        // Company B runs payroll for the SAME month.
        $this->generate($B['hr']);
        $bSlip = $this->row('payslips', ['user_id' => $B['employee']['user_id']]);
        $this->assertRuleOrKnownDefect(
            $bSlip !== null,
            'D-02',
            'payroll_runs is not company-scoped (UNIQUE month+year, no company_id): once ANY company has run a month, every other company is told "A payroll run for this period already exists" and cannot pay its staff for that month.'
        );
    }

    public function testAnotherCompanyCannotMarkMyRunPaid(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];
        $this->salary($A['employee']);
        $this->generate($A['hr']);
        $run = $this->row('payroll_runs', ['month' => $this->month, 'year' => $this->year]);

        $this->actAs($B['hr'])->submit("/hr/payroll/{$run['id']}/mark-paid");
        $this->assertRuleOrKnownDefect(
            $this->row('payroll_runs', ['id' => $run['id']])['status'] === 'processed',
            'D-12',
            "PayrollController::markPaid() has no company-scope check: HR/Admin of any company can mark another company's payroll run as paid."
        );
    }
}
