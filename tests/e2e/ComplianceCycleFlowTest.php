<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-05  Compliance: record -> due date -> reminder/overdue -> mark filed -> next cycle.
 *
 * Review/approval is NOT supported: there is no reviewer or approve action, and no route sets "in_progress"
 * (itemPayload() never writes status after creation) - filing is a single click by anyone with compliance.edit.
 */
final class ComplianceCycleFlowTest extends E2ETestCase
{
    private array $w;
    private int $typeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w      = $this->world();
        $this->typeId = (int) db_connect()->table('compliance_types')->where('name', 'GST Filing')->get()->getRow('id');
    }

    private function item(array $as, array $over = []): ?array
    {
        $A = $this->w['A'];
        $this->actAs($as)->submit('/compliance', $over + [
            'company_id' => $A['company']['id'], 'compliance_type_id' => $this->typeId, 'title' => 'GST-3B October', 'regulator' => 'GST Council', 'period' => '2026-10',
            'due_date' => date('Y-m-d', strtotime('+20 days')), 'recurrence' => 'none', 'responsible_user_id' => $A['admin']['user_id'], 'reminder_days_before' => '7', 'notes' => 'Monthly return',
        ]);

        return db_connect()->table('compliance_items')->orderBy('id', 'DESC')->get()->getRowArray() ?: null;
    }

    private function it(int $id): array
    {
        return $this->row('compliance_items', ['id' => $id]);
    }

    public function testRecordReminderOverdueAndFiling(): void
    {
        $A    = $this->w['A'];
        $item = $this->item($A['compliance']);

        // 1. Created pending, owner notified (the creator is not).
        $this->assertNotNull($item, 'created: ' . json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertSame('pending', $item['status']);
        $this->assertSame((string) $A['compliance']['user_id'], (string) $item['created_by']);
        $this->assertCount(1, $this->notificationsFor($A['admin']['user_id'], 'compliance_assigned'));

        // 2. Far from the due date: no reminder. Inside the lead time: one reminder, once a day.
        $this->actAs($A['admin'])->visit('/notifications/recent');
        $this->assertSame(0, $this->countRows('notifications', ['user_id' => $A['admin']['user_id'], 'type' => 'compliance_due_soon']));

        db_connect()->table('compliance_items')->where('id', $item['id'])->update(['due_date' => date('Y-m-d', strtotime('+3 days'))]);
        $this->actAs($A['admin'])->visit('/notifications/recent');
        $this->actAs($A['admin'])->visit('/notifications/recent');
        $this->assertSame(1, $this->countRows('notifications', ['user_id' => $A['admin']['user_id'], 'type' => 'compliance_due_soon']));
        $this->assertSame(0, $this->countRows('notifications', ['user_id' => $A['employee']['user_id']]), 'only the responsible person is reminded');

        // 3. Past the due date: the list flips it to overdue and the reminder escalates.
        db_connect()->table('compliance_items')->where('id', $item['id'])->update(['due_date' => date('Y-m-d', strtotime('-2 days'))]);
        $this->actAs($A['compliance'])->visit('/compliance');
        $this->assertSame('overdue', $this->it($item['id'])['status']);
        $this->actAs($A['admin'])->visit('/notifications/recent');
        $this->assertSame(1, $this->countRows('notifications', ['user_id' => $A['admin']['user_id'], 'type' => 'compliance_overdue']));

        // 4. Filing closes it, stamps the time, and it never turns overdue again.
        $this->actAs($A['compliance'])->submit("/compliance/{$item['id']}/mark-filed");
        $row = $this->it($item['id']);
        $this->assertSame('filed', $row['status']);
        $this->assertNotNull($row['filed_at']);
        $this->assertSame(1, $this->countRows('compliance_items'), 'a non-recurring item spawns no next cycle');
        $this->assertNotNull($this->row('activity_logs', ['module' => 'compliance', 'action' => 'update', 'record_id' => $item['id']]));
        $this->actAs($A['compliance'])->visit('/compliance');
        $this->assertSame('filed', $this->it($item['id'])['status']);
        $this->actAs($A['admin'])->visit('/notifications/recent');
        $this->assertSame(1, $this->countRows('notifications', ['user_id' => $A['admin']['user_id'], 'type' => 'compliance_overdue']), 'filed items stop reminding');
    }

    public function testFilingARecurringItemSchedulesTheNextCycleOnce(): void
    {
        $A    = $this->w['A'];
        $due  = date('Y-m-d', strtotime('+10 days'));
        $item = $this->item($A['compliance'], ['recurrence' => 'quarterly', 'due_date' => $due]);

        $this->actAs($A['compliance'])->submit("/compliance/{$item['id']}/mark-filed");
        $this->actAs($A['compliance'])->submit("/compliance/{$item['id']}/mark-filed");   // double click
        $this->assertSame(2, $this->countRows('compliance_items'), 'exactly one successor');

        $next = db_connect()->table('compliance_items')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertSame((string) $item['id'], (string) $next['previous_item_id']);
        $this->assertSame(date('Y-m-d', strtotime("$due +3 months")), $next['due_date']);
        $this->assertSame('pending', $next['status']);
        $this->assertSame('quarterly', $next['recurrence']);
        $this->assertSame((string) $A['admin']['user_id'], (string) $next['responsible_user_id']);
        $this->assertNull($next['period'], 'the period label is left for the user to set');
        $this->assertSame((string) $A['company']['id'], (string) $next['company_id']);
    }

    public function testGeneratorSchedulesAheadOfTheDueDateWithoutDuplicates(): void
    {
        $A    = $this->w['A'];
        $B    = $this->w['B'];
        $item = $this->item($A['compliance'], ['recurrence' => 'monthly', 'due_date' => date('Y-m-d', strtotime('+5 days')), 'reminder_days_before' => '7']);
        $far  = $this->item($A['compliance'], ['title' => 'Far away', 'recurrence' => 'monthly', 'due_date' => date('Y-m-d', strtotime('+60 days'))]);

        // Company B's HR-level user triggering the generator must not create company A's next cycle.
        $this->actAs($B['compliance'])->submit('/compliance/generate-recurring');
        $this->assertSame(2, $this->countRows('compliance_items'));

        $this->actAs($A['compliance'])->submit('/compliance/generate-recurring');
        $this->assertSame(3, $this->countRows('compliance_items'), 'only the item inside its lead time gets a successor');
        $this->assertTrue((new \App\Modules\Compliance\Models\ComplianceItemModel())->hasNextCycle((int) $item['id']));
        $this->assertFalse((new \App\Modules\Compliance\Models\ComplianceItemModel())->hasNextCycle((int) $far['id']));

        $this->actAs($A['compliance'])->submit('/compliance/generate-recurring');          // run again
        $this->actAs($A['compliance'])->submit("/compliance/{$item['id']}/mark-filed");    // then file it
        $this->assertSame(3, $this->countRows('compliance_items'), 'neither the generator nor filing adds a second successor');
    }

    public function testValidationAndCompanyBoundaries(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];

        $before = $this->countRows('compliance_items');
        foreach ([
            ['due_date' => ''], ['due_date' => '31-31-2026'], ['recurrence' => 'weekly'], ['compliance_type_id' => ''], ['reminder_days_before' => '-1'],
            ['company_id' => $B['company']['id']],
        ] as $bad) {
            $this->item($A['compliance'], $bad);
            $this->assertSame($before, $this->countRows('compliance_items'), 'rejected: ' . json_encode($bad));
        }

        $item = $this->item($A['compliance']);
        $this->actAs($B['compliance'])->visit("/compliance/{$item['id']}");
        $this->assertFlash('error', 'not found');
        $this->actAs($B['compliance'])->submit("/compliance/{$item['id']}/mark-filed");
        $this->assertSame('pending', $this->it($item['id'])['status']);
        $this->actAs($B['compliance'])->submit("/compliance/{$item['id']}/delete");
        $this->assertNotNull($this->row('compliance_items', ['id' => $item['id']]));
        $this->actAs($B['compliance'])->visit('/compliance');
        $this->assertStringNotContainsString('GST-3B October', $this->body());
    }

    public function testRolePermissions(): void
    {
        $A = $this->w['A'];
        foreach (['employee', 'hr', 'manager', 'accountant'] as $role) {
            $before = $this->countRows('compliance_items');
            $this->item($A[$role]);
            $this->assertSame($before, $this->countRows('compliance_items'), "$role cannot create compliance items");
        }
        $item = $this->item($A['compliance']);
        $this->actAs($A['employee'])->submit("/compliance/{$item['id']}/mark-filed");
        $this->assertSame('pending', $this->it($item['id'])['status']);
        $this->actAs($A['admin'])->submit("/compliance/{$item['id']}/mark-filed");
        $this->assertSame('filed', $this->it($item['id'])['status'], 'Company Admin can file');
    }

    public function testNoReviewStepAndNoWayToMarkInProgress(): void
    {
        $A    = $this->w['A'];
        $item = $this->item($A['compliance']);

        // Editing cannot move the status (status is not part of the update payload)...
        $this->actAs($A['compliance'])->submit("/compliance/{$item['id']}", [
            'company_id' => $A['company']['id'], 'compliance_type_id' => $this->typeId, 'title' => 'GST-3B October', 'due_date' => date('Y-m-d', strtotime('+20 days')),
            'recurrence' => 'none', 'status' => 'in_progress',
        ]);
        $this->assertSame('pending', $this->it($item['id'])['status'], "'in_progress' is unreachable through the UI");
        // ...and there is no approval route at all.
        $noRoute = false;
        try {
            $this->actAs($A['admin'])->submit("/compliance/{$item['id']}/approve");
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            $noRoute = true;
        }
        $this->assertTrue($noRoute, 'no approve route exists');
        $this->assertSame('pending', $this->it($item['id'])['status']);
    }

    public function testFilingRulesThatAreNotEnforced(): void
    {
        $A    = $this->w['A'];
        $item = $this->item($A['compliance']);
        $this->actAs($A['compliance'])->submit("/compliance/{$item['id']}/mark-filed");
        db_connect()->table('compliance_items')->where('id', $item['id'])->update(['filed_at' => '2026-01-01 10:00:00']);
        $this->actAs($A['compliance'])->submit("/compliance/{$item['id']}/mark-filed");

        $this->assertRuleOrKnownDefect(
            $this->it($item['id'])['filed_at'] === '2026-01-01 10:00:00',
            'D-32',
            'Marking an already-filed item as filed again overwrites filed_at (the original filing date/time is lost); there is no "already filed" guard.'
        );
    }

    public function testResponsiblePersonMustBelongToTheCompany(): void
    {
        $A    = $this->w['A'];
        $B    = $this->w['B'];
        $item = $this->item($A['compliance'], ['responsible_user_id' => $B['admin']['user_id']]);

        $this->assertRuleOrKnownDefect(
            $item === null,
            'D-33',
            "A compliance item can be assigned to a user of another company (responsible_user_id is not checked): that outsider is notified of, and reminded about, this company's obligation."
        );
    }
}
