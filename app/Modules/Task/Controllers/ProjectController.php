<?php

namespace App\Modules\Task\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Task\Models\ProjectModel;
use App\Modules\Task\Models\TaskModel;

class ProjectController extends BaseController
{
    protected ProjectModel $projectModel;
    protected CompanyModel $companyModel;
    protected UserModel $userModel;
    protected TaskModel $taskModel;

    public function __construct()
    {
        $this->projectModel = new ProjectModel();
        $this->companyModel = new CompanyModel();
        $this->userModel    = new UserModel();
        $this->taskModel    = new TaskModel();
    }

    public function index()
    {
        $filters = array_filter($this->request->getGet(['company_id', 'status']) ?? []);

        // Added AFTER array_filter(): a scope of 0 (account with no company yet) must survive and match nothing.
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        $projects   = $this->projectModel->filtered($filters)->findAll();
        $taskCounts = $this->taskModel->countsByProjectIds(array_column($projects, 'id'));

        foreach ($projects as &$p) {
            $p['task_count'] = $taskCounts[$p['id']] ?? 0;
        }
        unset($p);

        // Same data for every role — only Super Admin gets the richer
        // visual layout (projects_super_admin.php, stat cards + styled
        // table over this exact $projects array); every other role
        // keeps the existing projects.php view exactly as-is. Dashboard
        // stays on its own separate super_admin_index.php, untouched.
        $view = session('roleSlug') === 'super_admin' ? 'App\Modules\Task\projects_super_admin' : 'App\Modules\Task\projects';

        return view($view, [
            'title'      => 'Projects',
            'navActive'  => 'projects',
            'projects'   => $projects,
            'companies'  => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'statuses'   => ProjectModel::STATUSES,
            'filters'    => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Task\project_form', $this->formData(null));
    }

    public function store()
    {
        if (! $this->validate($this->projectModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only create projects for your own company.');
        }

        if (! $this->ownerMatchesCompany($this->request->getPost('owner_id'), (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'The owner must belong to the selected company.');
        }

        $id = $this->projectModel->insert($this->payload());

        $this->logActivity('project', 'create', $id, 'Created project ' . $this->request->getPost('name'));

        return redirect()->to('/projects')->with('success', 'Project created.');
    }

    public function show(int $id)
    {
        $project = $this->projectModel->filtered()->where('projects.id', $id)->first();

        if ($project === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $project['company_id'])) {
            return redirect()->to('/projects')->with('error', 'Project not found.');
        }

        $tasks = $this->taskModel->filtered(['project_id' => $id])->findAll();

        return view('App\Modules\Task\project_show', [
            'title'     => $project['name'],
            'navActive' => 'projects',
            'project'   => $project,
            'tasks'     => $tasks,
            'taskCounts' => [
                'total'     => count($tasks),
                'completed' => count(array_filter($tasks, static fn (array $t) => $t['status'] === 'completed')),
            ],
        ]);
    }

    public function edit(int $id)
    {
        $project = $this->projectModel->find($id);

        if ($project === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $project['company_id'])) {
            return redirect()->to('/projects')->with('error', 'Project not found.');
        }

        return view('App\Modules\Task\project_form', $this->formData($project));
    }

    public function update(int $id)
    {
        $project = $this->projectModel->find($id);

        if ($project === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $project['company_id'])) {
            return redirect()->to('/projects')->with('error', 'Project not found.');
        }

        if (! $this->validate($this->projectModel->validationRulesFor($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign projects to your own company.');
        }

        if (! $this->ownerMatchesCompany($this->request->getPost('owner_id'), (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'The owner must belong to the selected company.');
        }

        // skipValidation: already validated above via validationRulesFor($id)
        // with the real id interpolated — see TaskController::update()'s
        // identical comment for why this matters (confirmed live on the
        // analogous EmployeeProfileModel bug this session).
        $this->projectModel->skipValidation(true)->update($id, $this->payload());
        $this->logActivity('project', 'update', $id, 'Updated project #' . $id);

        return redirect()->to('/projects')->with('success', 'Project updated.');
    }

    public function delete(int $id)
    {
        $project = $this->projectModel->find($id);

        if ($project === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $project['company_id'])) {
            return redirect()->to('/projects')->with('error', 'Project not found.');
        }

        if ($this->projectModel->isInUse($id)) {
            return redirect()->to('/projects')->with('error', 'Cannot delete a project that still has tasks linked to it.');
        }

        $this->projectModel->delete($id);
        $this->logActivity('project', 'delete', $id, 'Deleted project #' . $id);

        return redirect()->to('/projects')->with('success', 'Project deleted.');
    }

    private function formData(?array $project): array
    {
        $companies = $this->scopedCompanyOptions($this->companyModel->optionsList());

        $nextCodeByCompany = [];
        foreach ($companies as $c) {
            $code = $this->projectModel->nextProjectCode((int) $c['id']);
            if ($code !== null) {
                $nextCodeByCompany[$c['id']] = $code;
            }
        }

        return [
            'title'     => $project ? 'Edit Project' : 'Add Project',
            'navActive' => 'projects',
            'project'   => $project,
            'companies' => $companies,
            'nextCodeByCompany' => $nextCodeByCompany,
            // listForOptionsWithCompany() — each row carries company_id, so the
            // view can filter the Owner dropdown client-side as Company changes
            // (same convention as Task/Meeting's own "Assigned To"/Participants).
            'users'     => $this->scopedUserOptions($this->userModel->listForOptionsWithCompany()),
            'statuses'  => ProjectModel::STATUSES,
        ];
    }

    /**
     * The Owner must belong to the project's own company — same rule and
     * reasoning as TaskController::assigneeMatchesCompany(). The Owner
     * dropdown's company filtering in the view is a convenience, not a
     * guarantee; this is the actual enforcement.
     */
    private function ownerMatchesCompany(?string $ownerId, int $companyId): bool
    {
        if (! $ownerId) {
            return true;
        }

        $profile = db_connect()->table('employee_profiles')
            ->where('user_id', (int) $ownerId)
            ->where('company_id', $companyId)
            ->countAllResults();

        return $profile > 0;
    }

    private function payload(): array
    {
        return [
            'company_id'  => (int) $this->request->getPost('company_id'),
            'project_code' => $this->request->getPost('project_code') ?: null,
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'status'      => $this->request->getPost('status') ?: 'active',
            'start_date'  => $this->request->getPost('start_date') ?: null,
            'end_date'    => $this->request->getPost('end_date') ?: null,
            'owner_id'    => $this->request->getPost('owner_id') ?: null,
        ];
    }
}
