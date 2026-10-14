<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-07  Expense management.
 *
 * What exists: company expenses/subscriptions (vendor, category, amount, renewal date, active/cancelled),
 * recurring-expense templates that auto-generate expense rows, renewal reminders, expense reports.
 * What does NOT exist (checked below): employee expense claims, approval/rejection, reimbursement payment,
 * and any accounting entry for an expense.
 */
final class ExpenseSubscriptionFlowTest extends E2ETestCase
{
    private array $w;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();
    }

    private function addExpense(array $as, array $over = []): ?array
    {
        $A = $this->w['A'];
        $this->actAs($as)->submit('/expenses', $over + [
            'company_id' => $A['company']['id'], 'vendor' => 'Cloud Host Inc', 'category' => 'Hosting', 'billing_cycle' => 'monthly',
            'amount' => '4999.50', 'renewal_date' => date('Y-m-d', strtotime('+3 days')), 'payment_method' => 'card', 'notes' => 'Prod servers', 'status' => 'active',
        ]);

        return db_connect()->table('expenses')->orderBy('id', 'DESC')->get()->getRowArray() ?: null;
    }

    public function testExpenseIsRecordedImmediatelyWithoutAnyApprovalStep(): void
    {
        $A  = $this->w['A'];
        $ex = $this->addExpense($A['accountant']);

        $this->assertNotNull($ex, 'expense created: ' . json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertSame('active', $ex['status']);
        $this->assertEquals(4999.50, (float) $ex['amount']);
        $this->assertSame((string) $A['accountant']['user_id'], (string) $ex['created_by']);
        $this->assertNotNull($this->row('activity_logs', ['module' => 'expense', 'action' => 'create', 'record_id' => $ex['id']]));

        // NOT SUPPORTED (G-01/G-02): no approval, reimbursement or accounting hooks exist.
        $cols = db_connect()->getFieldNames('expenses');
        foreach (['approval_status', 'approved_by', 'submitted_by', 'reimbursed_at', 'reimbursement_status', 'payment_id'] as $missing) {
            $this->assertNotContains($missing, $cols, "expenses has no $missing column - there is no approval/reimbursement workflow");
        }
        $this->assertSame(0, $this->countRows('payments'));
        $this->assertSame(0, $this->countRows('notifications', ['type' => 'expense_approved']), 'nothing notifies an approver');
    }

    public function testExpenseCrudValidationScopeAndPermissions(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];
        $ex = $this->addExpense($A['accountant']);

        foreach ([['vendor' => ''], ['category' => ''], ['amount' => '-1'], ['amount' => 'abc'], ['billing_cycle' => 'weekly'], ['amount' => '99999999999999']] as $bad) {
            $before = $this->countRows('expenses');
            $this->addExpense($A['accountant'], $bad);
            $this->assertSame($before, $this->countRows('expenses'), 'rejected: ' . json_encode($bad));
        }

        // Company boundaries.
        $before = $this->countRows('expenses');
        $this->addExpense($A['accountant'], ['company_id' => $B['company']['id']]);
        $this->assertSame($before, $this->countRows('expenses'));
        $this->actAs($B['accountant'])->visit('/expenses');
        $this->assertStringNotContainsString('Cloud Host Inc', $this->body());
        $this->actAs($B['accountant'])->visit("/expenses/{$ex['id']}/edit");
        $this->assertFlash('error', 'not found');
        $this->actAs($B['admin'])->submit("/expenses/{$ex['id']}/delete");
        $this->assertNotNull($this->row('expenses', ['id' => $ex['id']]));

        // Roles without expense.* cannot even add.
        foreach (['employee', 'hr', 'manager', 'compliance'] as $role) {
            $before = $this->countRows('expenses');
            $this->addExpense($A[$role]);
            $this->assertSame($before, $this->countRows('expenses'), "$role cannot add expenses");
        }

        // Update and cancel.
        $this->actAs($A['accountant'])->submit("/expenses/{$ex['id']}", [
            'company_id' => $A['company']['id'], 'vendor' => 'Cloud Host Inc', 'category' => 'Hosting', 'billing_cycle' => 'yearly', 'amount' => '60000', 'status' => 'cancelled',
        ]);
        $row = $this->row('expenses', ['id' => $ex['id']]);
        $this->assertSame('cancelled', $row['status']);
        $this->assertEquals(60000, (float) $row['amount']);
    }

    public function testRenewalReminderIsSentOnceADayToTheOwner(): void
    {
        $A  = $this->w['A'];
        $ex = $this->addExpense($A['accountant'], ['renewal_date' => date('Y-m-d', strtotime('+2 days'))]);
        $cancelled = $this->addExpense($A['accountant'], ['vendor' => 'Old Tool', 'renewal_date' => date('Y-m-d', strtotime('+2 days')), 'status' => 'cancelled']);
        $overdue   = $this->addExpense($A['accountant'], ['vendor' => 'Late Tool', 'renewal_date' => date('Y-m-d', strtotime('-2 days'))]);

        $this->actAs($A['accountant'])->visit('/notifications/recent');
        $due = $this->rows('notifications', ['user_id' => $A['accountant']['user_id'], 'type' => 'expense_due_soon']);
        $this->assertCount(1, $due);
        $this->assertSame((string) $ex['id'], (string) $due[0]['related_id']);
        $this->assertCount(1, $this->rows('notifications', ['user_id' => $A['accountant']['user_id'], 'type' => 'expense_overdue']));
        $this->assertSame((string) $overdue['id'], (string) $this->row('notifications', ['type' => 'expense_overdue'])['related_id']);
        $this->assertSame(0, $this->countRows('notifications', ['related_id' => $cancelled['id'], 'related_module' => 'expense']), 'cancelled subscriptions do not nag');

        // A second page view the same day does not create duplicates.
        $this->actAs($A['accountant'])->visit('/notifications/recent');
        $this->assertSame(2, $this->countRows('notifications', ['user_id' => $A['accountant']['user_id']]));
        // Nobody else is notified.
        $this->actAs($A['admin'])->visit('/notifications/recent');
        $this->assertSame(0, $this->countRows('notifications', ['user_id' => $A['admin']['user_id']]));
    }

    public function testRecurringExpenseGeneratesCatchUpRowsExactlyOnce(): void
    {
        $A = $this->w['A'];
        $this->actAs($A['accountant'])->submit('/recurring-expenses', [
            'company_id' => $A['company']['id'], 'title' => 'Office Rent', 'category' => 'Rent', 'amount' => '25000', 'frequency' => 'monthly',
            'start_date' => date('Y-m-d', strtotime('-2 months')), 'end_type' => 'occurrences', 'occurrences_total' => '3', 'description' => 'HQ',
        ]);
        $tpl = $this->row('recurring_expenses', ['title' => 'Office Rent']);
        $this->assertNotNull($tpl, json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertSame('active', $tpl['status']);
        $this->assertSame(0, $this->countRows('expenses'), 'nothing is generated until the generator runs');

        $this->actAs($A['accountant'])->submit('/recurring-expenses/generate-now');
        $rows = $this->rows('expenses', ['recurring_expense_id' => $tpl['id']]);
        $this->assertCount(3, $rows, 'the 2 missed months and the current one');
        $this->assertSame([1, 2, 3], array_map('intval', array_column($rows, 'occurrence_number')));
        $this->assertEquals(25000, (float) $rows[0]['amount']);
        $this->assertSame((string) $A['company']['id'], (string) $rows[0]['company_id']);
        $tpl = $this->row('recurring_expenses', ['id' => $tpl['id']]);
        $this->assertSame('completed', $tpl['status']);
        $this->assertNull($tpl['next_generation_date']);
        $this->assertSame(3, (int) $tpl['occurrences_generated']);

        // Running again (double click, cron + manual) creates nothing new.
        $this->actAs($A['accountant'])->submit('/recurring-expenses/generate-now');
        $this->assertSame(3, $this->countRows('expenses', ['recurring_expense_id' => $tpl['id']]));
    }

    public function testPausedTemplateDoesNotGenerateUntilResumed(): void
    {
        $A = $this->w['A'];
        $this->actAs($A['accountant'])->submit('/recurring-expenses', [
            'company_id' => $A['company']['id'], 'title' => 'SaaS', 'category' => 'Software', 'amount' => '100', 'frequency' => 'monthly',
            'start_date' => date('Y-m-d'), 'end_type' => 'occurrences', 'occurrences_total' => '12',
        ]);
        $t = $this->row('recurring_expenses', ['title' => 'SaaS']);

        $this->actAs($A['accountant'])->submit("/recurring-expenses/{$t['id']}/pause");
        $this->actAs($A['accountant'])->submit('/recurring-expenses/generate-now');
        $this->assertSame(0, $this->countRows('expenses', ['recurring_expense_id' => $t['id']]));

        $this->actAs($A['accountant'])->submit("/recurring-expenses/{$t['id']}/resume");
        $this->actAs($A['accountant'])->submit('/recurring-expenses/generate-now');
        $this->assertSame(1, $this->countRows('expenses', ['recurring_expense_id' => $t['id']]));
    }

    public function testGenerateNowOnlyProcessesTheViewersCompany(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];
        foreach ([[$A, 'A SaaS'], [$B, 'B SaaS']] as [$co, $title]) {
            $this->actAs($co['accountant'])->submit('/recurring-expenses', [
                'company_id' => $co['company']['id'], 'title' => $title, 'category' => 'Software', 'amount' => '100', 'frequency' => 'monthly',
                'start_date' => date('Y-m-d'), 'end_type' => 'occurrences', 'occurrences_total' => '12',
            ]);
        }
        $this->actAs($A['accountant'])->submit('/recurring-expenses/generate-now');

        $this->assertSame(1, $this->countRows('expenses', ['company_id' => $A['company']['id']]));
        $this->assertSame(0, $this->countRows('expenses', ['company_id' => $B['company']['id']]), "A's run must not generate B's expenses");

        $this->actAs($this->w['super'])->submit('/recurring-expenses/generate-now');
        $this->assertSame(1, $this->countRows('expenses', ['company_id' => $B['company']['id']]), 'Super Admin runs every company');
        $this->assertSame(1, $this->countRows('expenses', ['company_id' => $A['company']['id']]), 'and A is not duplicated');
    }

    public function testRecurringTemplateStatusMachine(): void
    {
        $A = $this->w['A'];
        $this->actAs($A['accountant'])->submit('/recurring-expenses', [
            'company_id' => $A['company']['id'], 'title' => 'Lifecycle', 'category' => 'Software', 'amount' => '100', 'frequency' => 'monthly',
            'start_date' => date('Y-m-d', strtotime('+10 days')), 'end_type' => 'occurrences', 'occurrences_total' => '2',
        ]);
        $t = $this->row('recurring_expenses', ['title' => 'Lifecycle']);

        $this->actAs($A['accountant'])->submit("/recurring-expenses/{$t['id']}/resume");   // not paused
        $this->assertSame('active', $this->row('recurring_expenses', ['id' => $t['id']])['status']);

        $this->actAs($A['accountant'])->submit("/recurring-expenses/{$t['id']}/pause");
        $this->actAs($A['accountant'])->submit("/recurring-expenses/{$t['id']}/pause");      // twice
        $this->assertSame('paused', $this->row('recurring_expenses', ['id' => $t['id']])['status']);
        $this->actAs($A['accountant'])->submit("/recurring-expenses/{$t['id']}/resume");
        $this->assertSame('active', $this->row('recurring_expenses', ['id' => $t['id']])['status']);

        // Cancelling needs expense.delete (Accountant/Admin have it), stops generation, and cannot be undone.
        $this->actAs($A['accountant'])->submit("/recurring-expenses/{$t['id']}/cancel");
        $row = $this->row('recurring_expenses', ['id' => $t['id']]);
        $this->assertSame('cancelled', $row['status']);
        $this->assertNull($row['next_generation_date']);
        $this->actAs($A['accountant'])->submit("/recurring-expenses/{$t['id']}/resume");
        $this->assertSame('cancelled', $this->row('recurring_expenses', ['id' => $t['id']])['status']);

        foreach ([['end_type' => 'occurrences', 'occurrences_total' => ''], ['end_type' => 'end_date', 'end_date' => date('Y-m-d', strtotime('-1 day'))], ['frequency' => 'weekly'], ['amount' => '0']] as $bad) {
            $before = $this->countRows('recurring_expenses');
            $this->actAs($A['accountant'])->submit('/recurring-expenses', $bad + [
                'company_id' => $A['company']['id'], 'title' => 'Bad', 'category' => 'Software', 'amount' => '100', 'frequency' => 'monthly',
                'start_date' => date('Y-m-d'), 'end_type' => 'occurrences', 'occurrences_total' => '2',
            ]);
            $this->assertSame($before, $this->countRows('recurring_expenses'), 'rejected: ' . json_encode($bad));
        }
    }
}
