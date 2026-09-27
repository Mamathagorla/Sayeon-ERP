<?php

namespace App\Modules\Task\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Department\Models\DepartmentModel;
use App\Modules\HR\Models\EmployeeProfileModel;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Task\Models\ProjectModel;
use App\Modules\Task\Models\TaskAttachmentModel;
use App\Modules\Task\Models\TaskChecklistItemModel;
use App\Modules\Task\Models\TaskCommentModel;
use App\Modules\Task\Models\TaskModel;
use App\Modules\Task\Models\TaskStatusHistoryModel;

class TaskController extends BaseController
{
    protected TaskModel $taskModel;
    protected TaskCommentModel $commentModel;
    protected TaskChecklistItemModel $checklistModel;
    protected TaskAttachmentModel $attachmentModel;
    protected TaskStatusHistoryModel $historyModel;
    protected CompanyModel $companyModel;
    protected DepartmentModel $departmentModel;
    protected UserModel $userModel;
    protected ProjectModel $projectModel;
    protected EmployeeProfileModel $employeeModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->taskModel      = new TaskModel();
        $this->commentModel   = new TaskCommentModel();
        $this->checklistModel = new TaskChecklistItemModel();
        $this->attachmentModel = new TaskAttachmentModel();
        $this->historyModel   = new TaskStatusHistoryModel();
        $this->companyModel   = new CompanyModel();
        $this->departmentModel = new DepartmentModel();
        $this->userModel      = new UserModel();
        $this->projectModel   = new ProjectModel();
        $this->employeeModel  = new EmployeeProfileModel();
        $this->notificationService = new NotificationService();
    }

    public function index()
    {
        $filters = $this->request->getGet(['company_id', 'department_id', 'project_id', 'status', 'priority', 'assigned_to', 'overdue', 'active']) ?? [];

        // Employee = "works their assigned tasks" (per RoleSeeder) â€” always
        // scoped to their own tasks, same as the Dashboard's personal view.
        // Not user-overridable: the assignee filter is dropped for them below.
        $isPersonalScope = session('roleSlug') === 'employee';
        if ($isPersonalScope) {
            $filters['assigned_to'] = session('userId');
        }

        $filters = array_filter($filters);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        $tasks = $this->taskModel->filtered($filters)->findAll();

        return view('App\Modules\Task\index', [
            'title'            => $isPersonalScope ? 'My Tasks' : 'Tasks',
            'navActive'        => 'tasks',
            'isPersonalScope'  => $isPersonalScope,
            'tasks'            => $tasks,
            'companies'        => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'departments'      => $this->departmentModel->optionsList(),
            'projects'         => $this->scopedProjects(),
            'users'            => $this->scopedUserOptions($this->userModel->listForOptions()),
            'statuses'         => TaskModel::STATUSES,
            'priorities'       => TaskModel::PRIORITIES,
            'filters'          => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Task\form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->taskModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // A Company Admin's own scope always wins over whatever
        // company_id was actually posted — closes the form-tampering
        // path where a scoped user could otherwise create a task
        // directly under a company that isn't theirs.
        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only create tasks for your own company.');
        }

        if (! $this->projectMatchesCompany($this->request->getPost('project_id'), (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'The selected project does not belong to the selected company.');
        }

        if (! $this->assigneeMatchesCompany($this->request->getPost('assigned_to'), (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'The assigned user does not belong to the selected company.');
        }

        $id = $this->taskModel->insert($this->taskPayload(true));

        $this->historyModel->record((int) $id, null, $this->request->getPost('status') ?: 'new', (int) $this->currentUserId());
        $this->logActivity('task', 'create', $id, 'Created task: ' . $this->request->getPost('title'));
        $this->notifyAssignment((int) $id, $this->request->getPost('title'), $this->request->getPost('assigned_to') ?: null);

        return redirect()->to('/tasks/' . $id)->with('success', 'Task created successfully.');
    }

    public function show(int $id)
    {
        $task = $this->taskModel->withRelations($id);

        if ($task === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $task['company_id'])) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        return view('App\Modules\Task\show', [
            'title'      => $task['title'],
            'navActive'  => 'tasks',
            'task'       => $task,
            'comments'   => $this->commentModel->forTask($id),
            'checklist'  => $this->checklistModel->forTask($id),
            'progress'   => $this->checklistModel->progressFor($id),
            'attachments' => $this->attachmentModel->forTask($id),
            'history'    => $this->historyModel->forTask($id),
            'statuses'   => TaskModel::STATUSES,
        ]);
    }

    public function edit(int $id)
    {
        $task = $this->taskModel->find($id);

        if ($task === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $task['company_id'])) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        return view('App\Modules\Task\form', $this->formData($task));
    }

    public function update(int $id)
    {
        $existing = $this->taskModel->find($id);

        if ($existing === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $existing['company_id'])) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        if (! $this->validate($this->taskModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Same form-tampering guard as store(): don't let an edit move
        // the task into a company outside the admin's own scope.
        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign tasks to your own company.');
        }

        if (! $this->projectMatchesCompany($this->request->getPost('project_id'), (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'The selected project does not belong to the selected company.');
        }

        if (! $this->assigneeMatchesCompany($this->request->getPost('assigned_to'), (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'The assigned user does not belong to the selected company.');
        }

        $newAssignedTo = $this->request->getPost('assigned_to') ?: null;

        $this->taskModel->update($id, $this->taskPayload(false));
        $this->logActivity('task', 'update', $id, 'Updated task #' . $id);

        if ((int) $newAssignedTo !== (int) $existing['assigned_to']) {
            $this->notifyAssignment($id, $this->request->getPost('title'), $newAssignedTo);
        }

        return redirect()->to('/tasks/' . $id)->with('success', 'Task updated successfully.');
    }

    public function delete(int $id)
    {
        $task = $this->taskModel->find($id);

        if ($task === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $task['company_id'])) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        $this->taskModel->delete($id);
        $this->logActivity('task', 'delete', $id, 'Deleted task #' . $id);

        return redirect()->to('/tasks')->with('success', 'Task deleted.');
    }

    public function updateStatus(int $id)
    {
        $task = $this->taskModel->find($id);

        if ($task === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $task['company_id'])) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        $newStatus = $this->request->getPost('status');

        if (! in_array($newStatus, TaskModel::STATUSES, true)) {
            return redirect()->back()->with('error', 'Invalid status.');
        }

        $data = ['status' => $newStatus];
        if ($newStatus === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        $this->taskModel->update($id, $data);
        $this->historyModel->record($id, $task['status'], $newStatus, (int) $this->currentUserId());
        $this->logActivity('task', 'update', $id, "Status changed: {$task['status']} â†’ {$newStatus}");

        return redirect()->to('/tasks/' . $id)->with('success', 'Status updated.');
    }

    public function addComment(int $id)
    {
        if ($this->authorizedTask($id) === null) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        if (! $this->request->getPost('comment')) {
            return redirect()->to('/tasks/' . $id . '#tab-comments')->with('error', 'Comment cannot be empty.');
        }

        $this->commentModel->insert([
            'task_id' => $id,
            'user_id' => $this->currentUserId(),
            'comment' => $this->request->getPost('comment'),
        ]);

        return redirect()->to('/tasks/' . $id . '#tab-comments')->with('success', 'Comment added.');
    }

    public function addChecklistItem(int $id)
    {
        if ($this->authorizedTask($id) === null) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        $title = $this->request->getPost('title');

        if (! $title) {
            return redirect()->to('/tasks/' . $id . '#tab-checklist')->with('error', 'Checklist item cannot be empty.');
        }

        $nextOrder = count($this->checklistModel->forTask($id));

        $this->checklistModel->insert(['task_id' => $id, 'title' => $title, 'sort_order' => $nextOrder]);

        return redirect()->to('/tasks/' . $id . '#tab-checklist')->with('success', 'Checklist item added.');
    }

    public function toggleChecklistItem(int $id, int $itemId)
    {
        if ($this->authorizedTask($id) === null) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        $item = $this->checklistModel->find($itemId);

        if ($item && (int) $item['task_id'] === $id) {
            $this->checklistModel->update($itemId, ['is_done' => $item['is_done'] ? 0 : 1]);
        }

        return redirect()->to('/tasks/' . $id . '#tab-checklist');
    }

    public function deleteChecklistItem(int $id, int $itemId)
    {
        if ($this->authorizedTask($id) === null) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        $this->checklistModel->delete($itemId);

        return redirect()->to('/tasks/' . $id . '#tab-checklist');
    }

    public function uploadAttachment(int $id)
    {
        if ($this->authorizedTask($id) === null) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        $file = $this->request->getFile('attachment');

        if ($file === null || ! $file->isValid()) {
            return redirect()->to('/tasks/' . $id . '#tab-attachments')->with('error', 'Please choose a valid file.');
        }

        if ($file->getSize() > 20 * 1024 * 1024) {
            return redirect()->to('/tasks/' . $id . '#tab-attachments')->with('error', 'File exceeds the 20MB limit.');
        }

        $storage = service('fileStorage');
        $path    = $storage->store($file, "tasks/{$id}");

        $this->attachmentModel->insert([
            'task_id'       => $id,
            'file_path'     => $path,
            'original_name' => $file->getClientName(),
            'file_size'     => $file->getSize(),
            'uploaded_by'   => $this->currentUserId(),
        ]);

        $this->logActivity('task', 'update', $id, 'Uploaded attachment: ' . $file->getClientName());

        return redirect()->to('/tasks/' . $id . '#tab-attachments')->with('success', 'Attachment uploaded.');
    }

    public function deleteAttachment(int $id, int $attachmentId)
    {
        if ($this->authorizedTask($id) === null) {
            return redirect()->to('/tasks')->with('error', 'Task not found.');
        }

        $attachment = $this->attachmentModel->find($attachmentId);

        if ($attachment && (int) $attachment['task_id'] === $id) {
            service('fileStorage')->delete($attachment['file_path']);
            $this->attachmentModel->delete($attachmentId);
        }

        return redirect()->to('/tasks/' . $id . '#tab-attachments')->with('success', 'Attachment removed.');
    }

    /**
     * Fetches a task and returns it only if it's within the current
     * viewer's company scope — null otherwise (not found, or belongs to
     * another company). Shared by every sub-resource action (comments,
     * checklist, attachments) so none of them can be reached for a task
     * outside a scoped user's own company just by knowing its id.
     */
    private function authorizedTask(int $id): ?array
    {
        $task = $this->taskModel->find($id);

        if ($task === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $task['company_id'])) {
            return null;
        }

        return $task;
    }

    /**
     * A task's project must belong to the same company as the task
     * itself — otherwise a task could point at another company's
     * project (a data-integrity break, and a company-boundary leak
     * since the project's name/detail page would then be reachable
     * from a task the viewer legitimately owns). Empty project_id
     * (project is optional) always passes.
     */
    private function projectMatchesCompany(?string $projectId, int $companyId): bool
    {
        if (! $projectId) {
            return true;
        }

        $project = $this->projectModel->find((int) $projectId);

        return $project !== null && (int) $project['company_id'] === $companyId;
    }

    /**
     * Same idea as projectMatchesCompany() — an assignee must belong
     * (via employee_profiles) to the task's own company, otherwise a
     * task could be handed to someone outside that company who'd then
     * see it in "My Tasks" and on their dashboard despite being scoped
     * elsewhere. Unassigned (empty) always passes.
     */
    private function assigneeMatchesCompany(?string $assignedTo, int $companyId): bool
    {
        if (! $assignedTo) {
            return true;
        }

        $profile = $this->employeeModel->byUserId((int) $assignedTo);

        return $profile !== null && (int) $profile['company_id'] === $companyId;
    }

    /**
     * Project options for the Task filter/form dropdowns, narrowed to
     * the viewer's company scope same as scopedCompanyOptions() — a
     * company-scoped viewer only ever sees/picks their own company's
     * projects; an unrestricted viewer (Super Admin / "All Companies")
     * sees every project.
     */
    private function scopedProjects(): array
    {
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        return $companyScope !== null
            ? $this->projectModel->optionsForCompany($companyScope)
            : $this->projectModel->orderBy('name', 'ASC')->findAll();
    }

    private function notifyAssignment(int $taskId, string $title, ?string $assignedTo): void
    {
        if (! $assignedTo || (int) $assignedTo === (int) $this->currentUserId()) {
            return;
        }

        $this->notificationService->notify(
            (int) $assignedTo,
            'task_assigned',
            'New task assigned: ' . $title,
            'You were assigned to "' . $title . '".',
            'task',
            $taskId
        );
    }

    private function formData(?array $task): array
    {
        return [
            'title'       => $task ? 'Edit Task' : 'Add Task',
            'navActive'   => 'tasks',
            'task'        => $task,
            'companies'   => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'departments' => $this->departmentModel->optionsList(),
            // listForOptionsWithCompany() (not the plain listForOptions()
            // every other module's dropdown uses) so each <option> can
            // carry a data-company-id for the client-side filter in
            // form.php — same reasoning as the 'projects' comment below.
            'users'       => $this->scopedUserOptions($this->userModel->listForOptionsWithCompany()),
            // A company-scoped viewer (Company Admin) can only ever pick
            // their own company, so this list is narrowed to match —
            // closes the leak of every other company's project names,
            // and makes the client-side filter in form.php redundant for
            // them. An unscoped viewer (Super Admin) still gets every
            // project since they pick the task's company freely; the
            // client-side filter narrows visible options as they change
            // that dropdown.
            'projects'    => $this->scopedProjects(),
            'statuses'    => TaskModel::STATUSES,
            'priorities'  => TaskModel::PRIORITIES,
        ];
    }

    private function taskPayload(bool $isNew): array
    {
        $data = [
            'title'         => $this->request->getPost('title'),
            'description'   => $this->request->getPost('description'),
            'company_id'    => (int) $this->request->getPost('company_id'),
            'department_id' => (int) $this->request->getPost('department_id'),
            'project_id'    => $this->request->getPost('project_id') ?: null,
            'priority'      => $this->request->getPost('priority'),
            'assigned_to'   => $this->request->getPost('assigned_to') ?: null,
            'start_date'    => $this->request->getPost('start_date') ?: null,
            'due_date'      => $this->request->getPost('due_date') ?: null,
            'status'        => $this->request->getPost('status') ?: 'new',
        ];

        if ($isNew) {
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }
}
