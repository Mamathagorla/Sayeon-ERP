<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-04  Meeting: create (provider "none" - no Google/Zoom call is made) -> invite participants -> minutes
 * -> action items -> convert to task -> task completion.
 *
 * "Meeting completion" is not a state: meetings have no status column, only a date.
 */
final class MeetingToTaskFlowTest extends E2ETestCase
{
    private array $w;
    private int $projectA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w        = $this->world();
        $this->projectA = $this->project($this->w['A']['company'], 'Alpha Rollout');
    }

    private function schedule(array $as, array $over = []): int
    {
        $A = $this->w['A'];
        $this->actAs($as)->submit('/meetings', $over + [
            'title' => 'Sprint planning', 'company_id' => $A['company']['id'], 'meeting_date' => date('Y-m-d', strtotime('+1 day')),
            'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'Board room', 'agenda' => 'Plan the sprint', 'provider' => 'none',
            'participant_user_ids' => [$A['employee']['user_id'], $A['hr']['user_id']],
        ]);
        $row = db_connect()->table('meetings')->orderBy('id', 'DESC')->get()->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    public function testMeetingToActionItemToTaskToCompletion(): void
    {
        $A  = $this->w['A'];
        $id = $this->schedule($A['manager']);

        // 1. Meeting + participants (organiser is always included) + in-app invitations, no external call.
        $m = $this->row('meetings', ['id' => $id]);
        $this->assertNotNull($m, 'meeting created: ' . json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertSame('none', $m['provider']);
        $this->assertSame('not_synced', $m['sync_status']);
        $this->assertNull($m['meeting_link'], 'no fake internal URL is generated');
        $this->assertSame(40, strlen($m['join_token']));
        $users = array_map('intval', array_column($this->rows('meeting_participants', ['meeting_id' => $id]), 'user_id'));
        sort($users);
        $expected = [$A['employee']['user_id'], $A['hr']['user_id'], $A['manager']['user_id']];
        sort($expected);
        $this->assertSame($expected, $users);
        $this->assertCount(1, $this->notificationsFor($A['employee']['user_id'], 'meeting_invite'));
        $this->assertCount(0, $this->notificationsFor($A['manager']['user_id'], 'meeting_invite'), 'the organiser is not invited to their own meeting');

        // 2. Minutes.
        $this->actAs($A['manager'])->submit("/meetings/$id/mom", ['mom' => 'Agreed to ship on Friday.']);
        $this->assertSame('Agreed to ship on Friday.', $this->row('meetings', ['id' => $id])['mom']);

        // 3. Action item for the employee, then converted into a task (department chosen at conversion).
        $due = date('Y-m-d', strtotime('+4 days'));
        $this->actAs($A['manager'])->submit("/meetings/$id/action-items", ['description' => 'Send the release notes', 'assigned_to' => $A['employee']['user_id'], 'due_date' => $due]);
        $item = $this->row('meeting_action_items', ['meeting_id' => $id]);
        $this->assertSame('pending', $item['status']);

        $this->actAs($A['manager'])->submit("/meetings/$id/action-items/{$item['id']}/convert", ['department_id' => $this->w['dept'], 'project_id' => $this->projectA]);
        $item = $this->row('meeting_action_items', ['id' => $item['id']]);

        $this->assertNotEmpty($item['linked_task_id'], 'Converting an action item must produce a task: ' . json_encode($this->flash('error')));
        $task = $this->row('tasks', ['id' => $item['linked_task_id']]);
        $this->assertNotNull($task);
        $this->assertSame('Follow-up: Send the release notes', $task['title']);
        $this->assertSame((string) $A['employee']['user_id'], (string) $task['assigned_to']);
        $this->assertSame($due, $task['due_date']);
        $this->assertSame((string) $A['company']['id'], (string) $task['company_id']);
        $this->assertSame('new', $task['status']);
        $this->assertSame(1, $this->countRows('task_status_history', ['task_id' => $task['id']]));

        // 4. Converting again does not create a second task.
        $this->actAs($A['manager'])->submit("/meetings/$id/action-items/{$item['id']}/convert", ['department_id' => $this->w['dept'], 'project_id' => $this->projectA]);
        $this->assertSame(1, $this->countRows('tasks'));
        $this->assertFlash('error', 'already been converted');

        // 5. The assignee works and completes the task; it appears in their task list.
        $this->actAs($A['employee'])->visit('/tasks');
        $this->assertStringContainsString('Follow-up: Send the release notes', $this->body());
        $this->actAs($A['employee'])->submit("/tasks/{$task['id']}/status", ['status' => 'completed']);
        $this->assertSame('completed', $this->row('tasks', ['id' => $task['id']])['status']);
    }

    public function testConversionNeedsAProjectOfTheMeetingCompanyAndLeavesNothingBehind(): void
    {
        $A  = $this->w['A'];
        $B  = $this->w['B'];
        $id = $this->schedule($A['manager']);
        $this->actAs($A['manager'])->submit("/meetings/$id/action-items", ['description' => 'Needs a project']);
        $item = $this->row('meeting_action_items', ['meeting_id' => $id]);
        $foreignProject = $this->project($B['company'], 'Beta Rollout');

        foreach ([null, 999999, $foreignProject] as $bad) {
            $this->actAs($A['manager'])->submit("/meetings/$id/action-items/{$item['id']}/convert", ['department_id' => $this->w['dept'], 'project_id' => $bad ?? '']);
            $this->assertFlash('error', 'project');
            $this->assertSame(0, $this->countRows('tasks'));
            $this->assertEmpty($this->row('meeting_action_items', ['id' => $item['id']])['linked_task_id']);
        }
        $this->actAs($A['manager'])->submit("/meetings/$id/action-items/{$item['id']}/convert", ['project_id' => $this->projectA]);
        $this->assertFlash('error', 'department');
    }

    public function testConvertedTaskNotifiesTheAssigneeAndTheActionItemFollowsTheTask(): void
    {
        $A  = $this->w['A'];
        $id = $this->schedule($A['manager']);
        $this->actAs($A['manager'])->submit("/meetings/$id/action-items", ['description' => 'Update the wiki', 'assigned_to' => $A['employee']['user_id']]);
        $item = $this->row('meeting_action_items', ['meeting_id' => $id]);
        $this->actAs($A['manager'])->submit("/meetings/$id/action-items/{$item['id']}/convert", ['department_id' => $this->w['dept'], 'project_id' => $this->projectA]);
        $item = $this->row('meeting_action_items', ['id' => $item['id']]);
        if (empty($item['linked_task_id'])) {
            $this->markTestSkipped('Conversion unexpectedly failed.');
        }

        $this->assertRuleOrKnownDefect(
            count($this->notificationsFor($A['employee']['user_id'], 'task_assigned')) === 1,
            'D-27',
            'A task created from a meeting action item never notifies its assignee (ActionItemTaskService does not call NotificationService, unlike TaskController).'
        );
    }

    public function testActionItemFollowsTaskCompletion(): void
    {
        $A  = $this->w['A'];
        $id = $this->schedule($A['manager']);
        $this->actAs($A['manager'])->submit("/meetings/$id/action-items", ['description' => 'Archive old files', 'assigned_to' => $A['employee']['user_id']]);
        $item = $this->row('meeting_action_items', ['meeting_id' => $id]);
        $this->actAs($A['manager'])->submit("/meetings/$id/action-items/{$item['id']}/convert", ['department_id' => $this->w['dept'], 'project_id' => $this->projectA]);
        $item = $this->row('meeting_action_items', ['id' => $item['id']]);
        if (empty($item['linked_task_id'])) {
            $this->markTestSkipped('Conversion unexpectedly failed.');
        }
        $this->actAs($A['employee'])->submit("/tasks/{$item['linked_task_id']}/status", ['status' => 'completed']);

        $this->assertRuleOrKnownDefect(
            $this->row('meeting_action_items', ['id' => $item['id']])['status'] === 'completed',
            'D-28',
            "Completing the linked task does not update the meeting action item (it stays 'pending'): the meeting's action list and the task list disagree."
        );
    }

    public function testActionItemValidationAndStatuses(): void
    {
        $A  = $this->w['A'];
        $B  = $this->w['B'];
        $id = $this->schedule($A['manager']);

        $this->actAs($A['manager'])->submit("/meetings/$id/action-items", ['description' => '']);
        $this->assertSame(0, $this->countRows('meeting_action_items'));

        $this->actAs($A['manager'])->submit("/meetings/$id/action-items", ['description' => 'Valid item']);
        $item = $this->row('meeting_action_items', ['meeting_id' => $id]);
        foreach (['in_progress', 'completed', 'pending'] as $status) {
            $this->actAs($A['manager'])->submit("/meetings/$id/action-items/{$item['id']}/status", ['status' => $status]);
            $this->assertSame($status, $this->row('meeting_action_items', ['id' => $item['id']])['status']);
        }

        // An invalid status must not corrupt or crash (D-29).
        $crashed = false;
        try {
            $this->actAs($A['manager'])->submit("/meetings/$id/action-items/{$item['id']}/status", ['status' => 'bogus']);
        } catch (\Throwable $e) {
            $crashed = true;
        }
        $this->assertRuleOrKnownDefect(
            ! $crashed && $this->row('meeting_action_items', ['id' => $item['id']])['status'] === 'pending',
            'D-29',
            'ActionItemController::updateStatus passes any posted status straight to the database: an invalid value ends in an unhandled database error instead of a validation message.'
        );

        // Another company cannot touch it.
        $this->actAs($B['manager'])->submit("/meetings/$id/action-items/{$item['id']}/status", ['status' => 'completed']);
        $this->assertSame('pending', $this->row('meeting_action_items', ['id' => $item['id']])['status']);
        $this->actAs($B['manager'])->submit("/meetings/$id/action-items/{$item['id']}/delete");
        $this->assertNotNull($this->row('meeting_action_items', ['id' => $item['id']]));
    }

    public function testActionItemAssigneeMustBelongToTheMeetingCompany(): void
    {
        $A  = $this->w['A'];
        $B  = $this->w['B'];
        $id = $this->schedule($A['manager']);

        $this->actAs($A['manager'])->submit("/meetings/$id/action-items", ['description' => 'Cross company', 'assigned_to' => $B['employee']['user_id']]);
        $item = $this->row('meeting_action_items', ['meeting_id' => $id]);

        $this->assertRuleOrKnownDefect(
            $item === null,
            'D-30',
            "An action item can be assigned to a user of ANOTHER company (assigned_to is not checked), and converting it then creates a task for that outside user."
        );
    }

    public function testMeetingValidationAndParticipantRules(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];

        $bad = [
            ['title' => ''], ['meeting_date' => 'not-a-date'], ['start_time' => '11:00', 'end_time' => '10:00'],
            ['company_id' => $B['company']['id']], ['participant_user_ids' => [$B['employee']['user_id']]], ['provider' => 'carrier-pigeon'],
        ];
        foreach ($bad as $over) {
            $this->schedule($A['manager'], $over);
        }
        $this->assertSame(0, $this->countRows('meetings'), 'nothing invalid is scheduled');
        $this->assertSame(0, $this->countRows('meeting_participants'));

        $id = $this->schedule($A['manager']);
        // External participant: one invite per e-mail (duplicate submission ignored), no mail sent (not configured).
        $this->actAs($A['manager'])->submit("/meetings/$id/participants", ['external_name' => 'Vendor Rep', 'external_email' => 'rep@vendor.test']);
        $this->actAs($A['manager'])->submit("/meetings/$id/participants", ['external_name' => 'Vendor Rep', 'external_email' => 'rep@vendor.test']);
        $this->assertSame(1, $this->countRows('meeting_participants', ['meeting_id' => $id, 'external_email' => 'rep@vendor.test']));
        // A user of another company cannot be added.
        $this->actAs($A['manager'])->submit("/meetings/$id/participants", ['user_id' => $B['employee']['user_id']]);
        $this->assertSame(0, $this->countRows('meeting_participants', ['meeting_id' => $id, 'user_id' => $B['employee']['user_id']]));
        // The same user twice is not duplicated.
        $crashed = false;
        try {
            $this->actAs($A['manager'])->submit("/meetings/$id/participants", ['user_id' => $A['employee']['user_id']]);
        } catch (\Throwable $e) {
            $crashed = true;
        }
        $this->assertSame(1, $this->countRows('meeting_participants', ['meeting_id' => $id, 'user_id' => $A['employee']['user_id']]), 'never stored twice');
        $this->assertRuleOrKnownDefect(! $crashed, 'D-31', 'Adding a user who is already a participant (double click) is not checked in ParticipantController::store: the unique key raises an unhandled database error (HTTP 500) instead of a friendly message.');
    }

    public function testMeetingVisibilityAndDeletion(): void
    {
        $A  = $this->w['A'];
        $B  = $this->w['B'];
        $id = $this->schedule($A['manager'], ['participant_user_ids' => [$A['employee']['user_id']]]);

        // Invited employee sees it (and joins through the token link); an uninvited employee does not; another company never.
        $outsider = $this->person($A['company'], 'employee', 'Not Invited', ['department_id' => $this->w['dept']]);
        $this->actAs($A['employee'])->visit("/meetings/$id");
        $this->assertTrue($this->res->isOK());
        $this->actAs($outsider)->visit("/meetings/$id");
        $this->assertFlash('error', 'not invited');
        $this->actAs($B['manager'])->visit("/meetings/$id");
        $this->assertFlash('error', 'not found');

        $token = $this->row('meetings', ['id' => $id])['join_token'];
        $this->actAs($A['employee'])->visit("/meetings/$id/join/$token");
        $this->assertTrue($this->res->isOK(), 'no external link => the in-app join page is shown');
        $this->actAs($A['employee'])->visit("/meetings/$id/join/" . str_repeat('a', 40));
        $this->assertFlash('error', 'invalid');
        $this->actAs($outsider)->visit("/meetings/$id/join/$token");
        $this->assertFlash('error', 'not invited');

        // Meeting completion is not modelled.
        $this->assertNotContains('status', db_connect()->getFieldNames('meetings'));

        // Delete: employees hold no meeting.delete; the organiser's manager does.
        $this->actAs($A['employee'])->submit("/meetings/$id/delete");
        $this->assertNotNull($this->row('meetings', ['id' => $id]));
        $this->actAs($A['manager'])->submit("/meetings/$id/delete");
        $this->assertNull($this->row('meetings', ['id' => $id]));
    }

    public function testUninvitedEmployeeCanStillEditAMeetingTheyCannotOpen(): void
    {
        $A  = $this->w['A'];
        $id = $this->schedule($A['manager'], ['participant_user_ids' => [$A['hr']['user_id']]]);
        $outsider = $A['employee'];   // not invited

        $this->actAs($outsider)->visit("/meetings/$id");
        $this->assertFlash('error', 'not invited');

        $this->actAs($outsider)->submit("/meetings/$id/action-items", ['description' => 'Injected by an uninvited employee']);
        $this->assertRuleOrKnownDefect(
            $this->countRows('meeting_action_items', ['meeting_id' => $id]) === 0,
            'D-24',
            "Employees are blocked from OPENING a meeting they were not invited to, but the action-item/participant/minutes routes only check the company, so an uninvited employee can add action items, participants and edit the minutes of that meeting."
        );
    }
}
