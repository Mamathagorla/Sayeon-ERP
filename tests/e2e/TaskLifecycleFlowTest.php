<?php

namespace Tests\E2E;

use Tests\Support\E2ETestCase;

/**
 * WF-03  Task management: create -> assign (+notification) -> in progress -> review -> completed / cancelled,
 * with status history, due-date reminders and company isolation.
 *
 * "Review/approval": 'review' exists as a task status only. There is no reviewer, no approve/reject action and
 * no rule about who may mark a task completed.
 */
final class TaskLifecycleFlowTest extends E2ETestCase
{
    private array $w;
    private int $projectA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w       = $this->world();
        $this->projectA = $this->project($this->w['A']['company'], 'Alpha Rollout');
    }

    private function newTask(array $as, array $over = []): ?array
    {
        $A = $this->w['A'];
        $this->actAs($as)->submit('/tasks', $over + [
            'title' => 'Prepare quarterly report', 'description' => 'Finance numbers', 'company_id' => $A['company']['id'], 'department_id' => $this->w['dept'],
            'project_id' => $this->projectA, 'priority' => 'high', 'assigned_to' => $A['employee']['user_id'], 'due_date' => date('Y-m-d', strtotime('+5 days')), 'status' => 'new',
        ]);

        return db_connect()->table('tasks')->orderBy('id', 'DESC')->get()->getRowArray() ?: null;
    }

    private function move(array $as, int $taskId, string $status): void
    {
        $this->actAs($as)->submit("/tasks/$taskId/status", ['status' => $status]);
    }

    public function testTaskFromCreationToCompletionWithHistoryAndNotifications(): void
    {
        $A    = $this->w['A'];
        $emp  = $A['employee'];
        $task = $this->newTask($A['manager']);

        $this->assertNotNull($task, 'task created: ' . json_encode($this->flash('errors') ?? $this->flash('error')));
        $this->assertSame('new', $task['status']);
        $this->assertSame((string) $emp['user_id'], (string) $task['assigned_to']);
        $this->assertSame((string) $A['manager']['user_id'], (string) $task['created_by']);
        $this->assertSame(36, strlen($task['uuid']));
        $this->assertCount(1, $this->notificationsFor($emp['user_id'], 'task_assigned'), 'assignee is notified');
        $this->assertCount(1, $this->rows('task_status_history', ['task_id' => $task['id']]));

        // The assignee works it: in_progress -> review -> completed.
        foreach (['in_progress', 'review', 'completed'] as $status) {
            $this->move($emp, (int) $task['id'], $status);
            $this->assertSame($status, $this->row('tasks', ['id' => $task['id']])['status']);
        }
        $done = $this->row('tasks', ['id' => $task['id']]);
        $this->assertNotNull($done['completed_at']);

        $hist = db_connect()->table('task_status_history')->where('task_id', $task['id'])->orderBy('id')->get()->getResultArray();
        $this->assertSame(['new', 'in_progress', 'review', 'completed'], array_column($hist, 'to_status'));
        $this->assertSame(['new', 'in_progress', 'review'], array_slice(array_column($hist, 'from_status'), 1, 3));
        $this->assertSame((string) $emp['user_id'], (string) $hist[3]['changed_by']);

        // G-07: finishing a task tells nobody (creator gets no notification).
        $this->assertCount(0, $this->notificationsFor($A['manager']['user_id']));

        // Completed tasks stop generating reminders.
        db_connect()->table('tasks')->where('id', $task['id'])->update(['due_date' => date('Y-m-d', strtotime('-3 days'))]);
        $this->actAs($emp)->visit('/notifications/recent');
        $this->assertSame(0, $this->countRows('notifications', ['user_id' => $emp['user_id'], 'type' => 'task_overdue']));
    }

    public function testOverdueAndDueSoonReminders(): void
    {
        $A    = $this->w['A'];
        $emp  = $A['employee'];
        $late = $this->newTask($A['manager'], ['title' => 'Late one', 'due_date' => date('Y-m-d', strtotime('-2 days'))]);
        $soon = $this->newTask($A['manager'], ['title' => 'Soon one', 'due_date' => date('Y-m-d', strtotime('+1 day'))]);
        $far  = $this->newTask($A['manager'], ['title' => 'Far one', 'due_date' => date('Y-m-d', strtotime('+20 days'))]);
        $can  = $this->newTask($A['manager'], ['title' => 'Cancelled one', 'due_date' => date('Y-m-d', strtotime('-2 days')), 'status' => 'cancelled']);

        $this->actAs($emp)->visit('/notifications/recent');
        $overdue = $this->rows('notifications', ['user_id' => $emp['user_id'], 'type' => 'task_overdue']);
        $dueSoon = $this->rows('notifications', ['user_id' => $emp['user_id'], 'type' => 'task_due_soon']);
        $this->assertCount(1, $overdue);
        $this->assertSame((string) $late['id'], (string) $overdue[0]['related_id']);
        $this->assertCount(1, $dueSoon);
        $this->assertSame((string) $soon['id'], (string) $dueSoon[0]['related_id']);

        // Re-polling the same day (new session) creates no duplicates.
        $this->actAs($emp)->visit('/notifications/recent');
        $this->assertSame(1, $this->countRows('notifications', ['user_id' => $emp['user_id'], 'type' => 'task_overdue']));
    }

    public function testReassignmentAndInvalidStatus(): void
    {
        $A    = $this->w['A'];
        $task = $this->newTask($A['manager']);

        $this->move($A['manager'], (int) $task['id'], 'finished-ish');
        $this->assertSame('new', $this->row('tasks', ['id' => $task['id']])['status']);
        $this->assertFlash('error', 'Invalid status');
        $this->assertSame(1, $this->countRows('task_status_history', ['task_id' => $task['id']]));

        // Re-assigning notifies the new assignee only.
        $this->actAs($A['manager'])->submit("/tasks/{$task['id']}", [
            'title' => 'Prepare quarterly report', 'company_id' => $A['company']['id'], 'department_id' => $this->w['dept'], 'project_id' => $this->projectA,
            'priority' => 'high', 'assigned_to' => $A['hr']['user_id'], 'status' => 'new',
        ]);
        $this->assertSame((string) $A['hr']['user_id'], (string) $this->row('tasks', ['id' => $task['id']])['assigned_to']);
        $this->assertCount(1, $this->notificationsFor($A['hr']['user_id'], 'task_assigned'));
        $this->assertCount(1, $this->notificationsFor($A['employee']['user_id'], 'task_assigned'), 'old assignee keeps only the original notice');
    }

    public function testValidationAndCompanyBoundaries(): void
    {
        $A = $this->w['A'];
        $B = $this->w['B'];
        $projectB = $this->project($B['company'], 'Beta Rollout');

        $before = $this->countRows('tasks');
        $this->newTask($A['manager'], ['title' => '']);
        $this->newTask($A['manager'], ['priority' => 'asap']);
        $this->newTask($A['manager'], ['project_id' => '']);                               // project is mandatory
        $this->newTask($A['manager'], ['project_id' => $projectB]);                        // another company's project
        $this->newTask($A['manager'], ['assigned_to' => $B['employee']['user_id']]);       // another company's person
        $this->newTask($A['manager'], ['company_id' => $B['company']['id']]);              // another company
        $this->assertSame($before, $this->countRows('tasks'));

        $task = $this->newTask($A['manager']);
        $this->actAs($B['manager'])->visit('/tasks/' . $task['id']);
        $this->assertFlash('error', 'not found');
        $this->move($B['manager'], (int) $task['id'], 'completed');
        $this->assertSame('new', $this->row('tasks', ['id' => $task['id']])['status']);
        $this->actAs($B['manager'])->submit('/tasks/' . $task['id'] . '/delete');
        $this->assertNull($this->row('tasks', ['id' => $task['id']])['deleted_at'], 'cross-company delete refused');
    }

    public function testEmployeeListIsPersonalButOwnershipIsNotEnforced(): void
    {
        $A      = $this->w['A'];
        $colleague = $this->person($A['company'], 'employee', 'Colleague Two', ['department_id' => $this->w['dept']]);
        $mine   = $this->newTask($A['manager'], ['title' => 'My own job']);
        $theirs = $this->newTask($A['manager'], ['title' => 'Colleague job', 'assigned_to' => $colleague['user_id']]);

        $this->actAs($A['employee'])->visit('/tasks');
        $this->assertStringContainsString('My own job', $this->body());
        $this->assertStringNotContainsString('Colleague job', $this->body(), 'the list shows only my tasks');

        // D-22: ...but the task itself is open to any employee of the company: they can read and change it.
        $this->actAs($A['employee'])->visit('/tasks/' . $theirs['id']);
        $canRead = $this->res->isOK();
        $this->move($A['employee'], (int) $theirs['id'], 'completed');
        $changed = $this->row('tasks', ['id' => $theirs['id']])['status'] === 'completed';

        $this->assertRuleOrKnownDefect(
            ! $canRead && ! $changed,
            'D-22',
            "Employees only LIST their own tasks, but task show/updateStatus/comment/checklist/attachment routes only check the company: any employee can open a colleague's task by id and mark it completed."
        );
    }

    public function testRolesAndDeletion(): void
    {
        $A    = $this->w['A'];
        $task = $this->newTask($A['manager']);

        foreach (['hr', 'accountant', 'compliance'] as $role) {
            $this->actAs($A[$role])->visit('/tasks');
            $this->assertDenied("$role has no task.view");
        }
        $this->actAs($A['employee'])->submit("/tasks/{$task['id']}/delete");
        $this->assertNull($this->row('tasks', ['id' => $task['id']])['deleted_at'], 'employee cannot delete');

        $this->actAs($A['manager'])->submit("/tasks/{$task['id']}/delete");
        $this->assertNotNull($this->row('tasks', ['id' => $task['id']])['deleted_at'], 'soft delete keeps history');
        $this->assertSame(1, $this->countRows('task_status_history', ['task_id' => $task['id']]));
        $this->actAs($A['manager'])->visit('/tasks/' . $task['id']);
        $this->assertFlash('error', 'not found');
    }

    public function testReopeningACompletedTaskClearsCompletionDate(): void
    {
        $A    = $this->w['A'];
        $task = $this->newTask($A['manager']);
        $this->move($A['employee'], (int) $task['id'], 'completed');
        $this->move($A['employee'], (int) $task['id'], 'in_progress');

        $row = $this->row('tasks', ['id' => $task['id']]);
        $this->assertRuleOrKnownDefect(
            $row['status'] !== 'in_progress' || $row['completed_at'] === null,
            'D-21',
            'Re-opening a completed task leaves completed_at set (and any status can follow any status), so reports that count completed_at show it as finished while it is in progress.'
        );
    }

    public function testCommentsChecklistAndHistoryAreCompanyScoped(): void
    {
        $A    = $this->w['A'];
        $task = $this->newTask($A['manager']);

        $this->actAs($A['employee'])->submit("/tasks/{$task['id']}/comments", ['comment' => 'On it']);
        $this->actAs($A['manager'])->submit("/tasks/{$task['id']}/checklist", ['title' => 'Collect data']);
        $this->assertSame(1, $this->countRows('task_comments', ['task_id' => $task['id']]));
        $this->assertSame(1, $this->countRows('task_checklist_items', ['task_id' => $task['id']]));

        $this->actAs($this->w['B']['manager'])->submit("/tasks/{$task['id']}/comments", ['comment' => 'Intruder']);
        $this->assertSame(1, $this->countRows('task_comments', ['task_id' => $task['id']]));
        $this->actAs($A['manager'])->submit("/tasks/{$task['id']}/comments", ['comment' => '']);
        $this->assertSame(1, $this->countRows('task_comments', ['task_id' => $task['id']]), 'empty comment refused');
    }
}
