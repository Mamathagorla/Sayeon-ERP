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
        $filters = $this->request->getGet(['company_id', 'status']) ?? [];

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        $projects   = $this->projectModel->filtered(array_filter($filters))->findAll();
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

        if (! $this->validate($this->projectModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign projects to your own company.');
        }

        $this->projectModel->update($id, $this->payload());
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
        return [
            'title'     => $project ? 'Edit Project' : 'Add Project',
            'navActive' => 'projects',
            'project'   => $project,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'users'     => $this->scopedUserOptions($this->userModel->listForOptions()),
            'statuses'  => ProjectModel::STATUSES,
        ];
    }

    private function payload(): array
    {
        return [
            'company_id'  => (int) $this->request->getPost('company_id'),
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'status'      => $this->request->getPost('status') ?: 'active',
            'start_date'  => $this->request->getPost('start_date') ?: null,
            'end_date'    => $this->request->getPost('end_date') ?: null,
            'owner_id'    => $this->request->getPost('owner_id') ?: null,
        ];
    }
}
