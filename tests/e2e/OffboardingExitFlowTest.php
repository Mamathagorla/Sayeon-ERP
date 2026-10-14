<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-01 (last part)  Resignation/termination -> checklist by owner role -> stages -> exit completed
 * -> employee status -> payroll exclusion -> relieving letter.
 */
final class OffboardingExitFlowTest extends E2ETestCase
{
    private array $w;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();
    }

    private function resign(array $emp): int
    {
        $this->actAs($emp)->submit('/hr/offboarding', [
            'exit_type' => 'ignored-for-employees', 'reason' => 'Better opportunity', 'exit_date' => date('Y-m-d', strtotime('+30 days')),
            'last_working_day' => date('Y-m-d', strtotime('+30 days')), 'notice_period_days' => '30',
        ]);
        $row = db_connect()->table('offboarding_records')->orderBy('id', 'DESC')->get()->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function task(int $recordId, string $title): array
    {
        return db_connect()->table('offboarding_tasks')->where(['offboarding_record_id' => $recordId, 'title' => $title])->get()->getRowArray();
    }

    private function complete(array $actor, int $recordId, string $title): void
    {
        $t = $this->task($recordId, $title);
        $this->actAs($actor)->submit("/hr/offboarding/$recordId/tasks/{$t['id']}", ['status' => 'completed']);
    }

    /** Marks every checklist item done, as the owners would, so HR may close the exit. */
    private function finishChecklist(int $recordId): void
    {
        db_connect()->table('offboarding_tasks')->where('offboarding_record_id', $recordId)->update(['status' => 'completed']);
    }

    private function exitStatus(int $id): string
    {
        return $this->row('offboarding_records', ['id' => $id])['status'];
    }

    public function testResignationThroughChecklistToExitCompleted(): void
    {
        $A   = $this->w['A'];
        $emp = $A['employee'];

        // 1. The employee resigns: record owned by their company, checklist seeded, manager asked to review.
        $id  = $this->resign($emp);
        $rec = $this->row('offboarding_records', ['id' => $id]);
        $this->assertNotNull($rec, 'resignation record: ' . json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertSame('resignation', $rec['exit_type'], 'employees can only resign - exit type is forced');
        $this->assertSame('resignation_submitted', $rec['status']);
        $this->assertSame((string) $A['company']['id'], (string) $rec['company_id']);
        $this->assertSame((string) $emp['profile_id'], (string) $rec['employee_profile_id']);
        $this->assertSame(11, $this->countRows('offboarding_tasks', ['offboarding_record_id' => $id]));
        $this->assertCount(1, $this->notificationsFor($A['manager']['user_id'], 'offboarding_review'));

        // A second resignation while one is open is refused (no duplicate records).
        $this->resign($emp);
        $this->assertSame(1, $this->countRows('offboarding_records'));
        $this->assertFlash('error', 'cannot submit a resignation right now');

        // 2. Checklist items can only be actioned by their owner role (HR and Super Admin can do any).
        $mgrTask = $this->task($id, 'Review resignation and provide recommendation');
        $this->actAs($emp)->submit("/hr/offboarding/$id/tasks/{$mgrTask['id']}", ['status' => 'completed']);
        $this->assertSame('pending', $this->row('offboarding_tasks', ['id' => $mgrTask['id']])['status'], "employee cannot do the manager's task");
        $this->actAs($this->w['B']['manager'])->submit("/hr/offboarding/$id/tasks/{$mgrTask['id']}", ['status' => 'completed']);
        $this->assertSame('pending', $this->row('offboarding_tasks', ['id' => $mgrTask['id']])['status'], "another company's manager cannot");

        $this->complete($A['manager'], $id, 'Review resignation and provide recommendation');
        $this->assertSame('completed', $this->row('offboarding_tasks', ['id' => $mgrTask['id']])['status']);
        $this->assertSame((string) $A['manager']['user_id'], (string) $this->row('offboarding_tasks', ['id' => $mgrTask['id']])['completed_by']);

        // 3. Every owner completes their part; the stage follows the checklist automatically.
        $this->complete($A['hr'], $id, 'Review and approve/reject the exit request');
        $this->assertContains($this->exitStatus($id), ['hr_approval', 'exit_initiated', 'handover'], 'moves past manager review/HR approval');
        $this->assertNotNull($this->row('offboarding_records', ['id' => $id])['hr_approved_at'] ?? null);

        $this->complete($emp, $id, 'Prepare handover notes');
        $this->complete($A['manager'], $id, 'Review and accept handover');
        $this->complete($A['manager'], $id, 'Obtain department/team clearance');
        $this->complete($emp, $id, 'Return company assets (laptop, ID card, etc.)');
        $this->complete($A['admin'], $id, 'Confirm assets received');
        $this->complete($A['admin'], $id, 'Revoke system access, email & logins');
        $this->assertSame('completed', $this->row('offboarding_records', ['id' => $id])['handover_status']);
        $this->assertSame('completed', $this->row('offboarding_records', ['id' => $id])['access_revocation_status']);
        $this->complete($A['accountant'], $id, 'Process final settlement & dues');
        $this->assertSame('completed', $this->row('offboarding_records', ['id' => $id])['final_settlement_status']);
        $this->complete($A['hr'], $id, 'Conduct exit interview');
        $this->complete($A['hr'], $id, 'Issue relieving letter & experience certificate');
        $this->assertSame('final_documents', $this->exitStatus($id), 'all tasks done -> waiting for HR to close the exit');
        $this->assertSame('active', $this->row('employee_profiles', ['id' => $emp['profile_id']])['status'], 'employee stays active until the exit is closed');

        // 4. Only HR can close it.
        $this->actAs($A['manager'])->submit("/hr/offboarding/$id/status", ['status' => 'exit_completed']);
        $this->assertSame('final_documents', $this->exitStatus($id));
        $this->actAs($emp)->submit("/hr/offboarding/$id/status", ['status' => 'exit_completed']);
        $this->assertSame('final_documents', $this->exitStatus($id));

        $this->actAs($A['hr'])->submit("/hr/offboarding/$id/status", ['status' => 'exit_completed']);
        $rec = $this->row('offboarding_records', ['id' => $id]);
        $this->assertSame('exit_completed', $rec['status']);
        $this->assertNotNull($rec['completed_at']);
        $this->assertSame('resigned', $this->row('employee_profiles', ['id' => $emp['profile_id']])['status']);
        $this->assertCount(1, $this->rows('notifications', ['user_id' => $emp['user_id'], 'type' => 'offboarding_status', 'title' => 'Exit status update']) , 'the employee is told the exit is completed');

        // History is preserved - nothing was deleted.
        $this->assertNotNull($this->row('users', ['id' => $emp['user_id']]));
        $this->assertSame(11, $this->countRows('offboarding_tasks', ['offboarding_record_id' => $id]));
    }

    public function testExitedEmployeeIsLeftOutOfPayroll(): void
    {
        $A = $this->w['A'];
        $this->salary($A['employee']);
        $this->salary($A['manager']);

        $id = $this->resign($A['employee']);
        $this->finishChecklist($id);
        $this->actAs($A['hr'])->submit("/hr/offboarding/$id/status", ['status' => 'exit_completed']);

        $this->actAs($A['hr'])->submit('/hr/payroll/generate', ['month' => (int) date('n'), 'year' => (int) date('Y')]);
        $this->assertSame(0, $this->countRows('payslips', ['user_id' => $A['employee']['user_id']]), 'a resigned employee is not paid');
        $this->assertSame(1, $this->countRows('payslips', ['user_id' => $A['manager']['user_id']]));
    }

    public function testTerminationByHrAndRescindedExit(): void
    {
        $A = $this->w['A'];

        // Only HR may initiate an exit for somebody else.
        $this->actAs($A['manager'])->submit('/hr/offboarding', ['employee_profile_id' => $A['employee']['profile_id'], 'exit_type' => 'termination', 'exit_date' => date('Y-m-d')]);
        $this->assertSame(0, $this->countRows('offboarding_records'));
        $this->actAs($this->w['B']['hr'])->submit('/hr/offboarding', ['employee_profile_id' => $A['employee']['profile_id'], 'exit_type' => 'termination', 'exit_date' => date('Y-m-d')]);
        $this->assertSame(0, $this->countRows('offboarding_records'), "another company's HR cannot terminate");

        $this->actAs($A['hr'])->submit('/hr/offboarding', ['employee_profile_id' => $A['employee']['profile_id'], 'exit_type' => 'termination', 'exit_date' => date('Y-m-d'), 'reason' => 'Policy breach']);
        $id = (int) db_connect()->table('offboarding_records')->get()->getRow('id');
        $this->assertGreaterThan(0, $id);
        $this->assertSame('termination', $this->row('offboarding_records', ['id' => $id])['exit_type']);

        $this->actAs($A['hr'])->submit('/hr/offboarding', ['employee_profile_id' => $A['employee']['profile_id'], 'exit_type' => 'termination', 'exit_date' => date('Y-m-d')]);
        $this->assertSame(1, $this->countRows('offboarding_records'), 'no second open exit for the same employee');

        // Rescinding before completion leaves the employee active and allows a new exit later.
        $this->actAs($A['hr'])->submit("/hr/offboarding/$id/status", ['status' => 'rescinded']);
        $this->assertSame('rescinded', $this->exitStatus($id));
        $this->assertSame('active', $this->row('employee_profiles', ['id' => $A['employee']['profile_id']])['status']);
        $this->actAs($A['hr'])->submit('/hr/offboarding', ['employee_profile_id' => $A['employee']['profile_id'], 'exit_type' => 'termination', 'exit_date' => date('Y-m-d')]);
        $this->assertSame(2, $this->countRows('offboarding_records'));

        // Termination -> status "terminated".
        $second = (int) db_connect()->table('offboarding_records')->orderBy('id', 'DESC')->get()->getRow('id');
        $this->actAs($A['hr'])->submit("/hr/offboarding/$second/status", ['status' => 'exit_completed']);
        $this->assertSame('active', $this->row('employee_profiles', ['id' => $A['employee']['profile_id']])['status'], 'cannot be closed while checklist items are open');
        $this->finishChecklist($second);
        $this->actAs($A['hr'])->submit("/hr/offboarding/$second/status", ['status' => 'exit_completed']);
        $this->assertSame('terminated', $this->row('employee_profiles', ['id' => $A['employee']['profile_id']])['status']);
    }

    public function testCompanyIsolationOfExitRecords(): void
    {
        $A  = $this->w['A'];
        $B  = $this->w['B'];
        $id = $this->resign($A['employee']);

        $this->actAs($B['hr'])->visit("/hr/offboarding/$id");
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'not found');
        $this->actAs($B['hr'])->submit("/hr/offboarding/$id/status", ['status' => 'exit_completed']);
        $this->assertSame('resignation_submitted', $this->exitStatus($id));
        $this->actAs($B['hr'])->submit("/hr/offboarding/$id/delete");
        $this->assertNotNull($this->row('offboarding_records', ['id' => $id]));
    }

    public function testExitStageRulesThatAreNotEnforced(): void
    {
        $A  = $this->w['A'];
        $id = $this->resign($A['employee']);

        // D-03: HR can jump straight to "exit completed" with every checklist item still open.
        $this->actAs($A['hr'])->submit("/hr/offboarding/$id/status", ['status' => 'exit_completed']);
        $open = $this->countRows('offboarding_tasks', ['offboarding_record_id' => $id, 'status' => 'pending']);
        $this->assertRuleOrKnownDefect(
            $this->exitStatus($id) !== 'exit_completed' || $open === 0,
            'D-03',
            'Offboarding::updateStatus accepts any stage: a resignation can be closed ("exit_completed", employee set to resigned) straight from "resignation_submitted" with the final-settlement, asset-return and access-revocation tasks still pending.'
        );
    }

    public function testExitCompletedDisablesTheLogin(): void
    {
        $A  = $this->w['A'];
        $id = $this->resign($A['employee']);
        $this->finishChecklist($id);
        $this->actAs($A['hr'])->submit("/hr/offboarding/$id/status", ['status' => 'exit_completed']);
        $this->assertSame('resigned', $this->row('employee_profiles', ['id' => $A['employee']['profile_id']])['status']);
        $this->assertSame('inactive', $this->row('users', ['id' => $A['employee']['user_id']])['status']);

        $this->anonymous()->submit('/auth/attempt-login', ['email' => $A['employee']['email'], 'password' => self::PASSWORD]);
        $loggedIn = ! empty($_SESSION['isLoggedIn']);

        $this->assertRuleOrKnownDefect(
            ! $loggedIn,
            'D-05',
            'Completing an exit only flips employee_profiles.status; users.status stays "active", so a resigned/terminated employee can still sign in (access revocation is just a checklist task).'
        );
    }

    public function testRescindingACompletedExitDoesNotRestoreTheEmployee(): void
    {
        $A  = $this->w['A'];
        $id = $this->resign($A['employee']);
        $this->finishChecklist($id);
        $this->actAs($A['hr'])->submit("/hr/offboarding/$id/status", ['status' => 'exit_completed']);
        $this->actAs($A['hr'])->submit("/hr/offboarding/$id/status", ['status' => 'rescinded']);
        $this->assertFlash('error', 'closed');

        $this->assertRuleOrKnownDefect(
            $this->exitStatus($id) !== 'rescinded' || $this->row('employee_profiles', ['id' => $A['employee']['profile_id']])['status'] === 'active',
            'D-04',
            'A completed exit can be moved to "rescinded" (or any earlier stage) but the employee stays "resigned": record and employee status contradict each other and the person remains excluded from payroll.'
        );
    }

    public function testRelievingLetterNeedsACompanyTemplate(): void
    {
        $A  = $this->w['A'];
        $id = $this->resign($A['employee']);
        $this->actAs($A['hr'])->submit("/hr/offboarding/$id/status", ['status' => 'exit_completed']);

        $this->actAs($A['hr'])->visit("/hr/offboarding/$id/relieving-letter/pdf");
        $this->assertTrue($this->res->isRedirect());
        $this->assertFlash('error', 'No active Relieving Letter template');

        $this->template($A['company'], 'relieving_letter', '<p>{{employee_name}} served as {{designation}} until {{last_working_day}}.</p>');
        $this->downloadArchived("/hr/offboarding/$id/relieving-letter/pdf", 'relieving_letter', $id);
        $this->assertTrue($this->res->isOK(), json_encode($this->flash('error')));
        $this->assertStringStartsWith('%PDF', $this->body());
        $this->assertSame(1, $this->countRows('generated_documents', ['document_type' => 'relieving_letter']));

        // Another company's HR cannot get it.
        $this->actAs($this->w['B']['hr'])->visit("/hr/offboarding/$id/relieving-letter/pdf");
        $this->assertTrue($this->res->isRedirect());
    }

    public function testFinalSettlementHasNoFinancialSideEffects(): void
    {
        // G-04: "Process final settlement & dues" is only a checklist item - no amount, no payment, no accounting entry.
        $A  = $this->w['A'];
        $id = $this->resign($A['employee']);
        $this->complete($A['accountant'], $id, 'Process final settlement & dues');
        $this->assertSame('completed', $this->row('offboarding_records', ['id' => $id])['final_settlement_status']);
        $this->assertSame(0, $this->countRows('payments'));
        $this->assertSame(0, $this->countRows('payslips'));
        $this->assertSame(0, $this->countRows('expenses'));
    }
}
