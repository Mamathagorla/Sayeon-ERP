<?php

namespace App\Modules\Department\Controllers;

use App\Controllers\BaseController;
use App\Modules\Department\Models\DepartmentModel;

class DepartmentController extends BaseController
{
    protected DepartmentModel $departmentModel;

    public function __construct()
    {
        $this->departmentModel = new DepartmentModel();
    }

    public function index()
    {
        return view('App\Modules\Department\index', [
            'title'       => 'Departments',
            'navActive'   => 'departments',
            'departments' => $this->departmentModel->optionsList(),
        ]);
    }

    public function create()
    {
        return view('App\Modules\Department\form', ['title' => 'Add Department', 'navActive' => 'departments', 'department' => null]);
    }

    public function store()
    {
        if (! $this->validate($this->departmentModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = $this->departmentModel->insert([
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
        ]);

        $this->logActivity('department', 'create', $id, 'Created department ' . $this->request->getPost('name'));

        return redirect()->to('/departments')->with('success', 'Department created.');
    }

    public function edit(int $id)
    {
        $department = $this->departmentModel->find($id);

        if ($department === null) {
            return redirect()->to('/departments')->with('error', 'Department not found.');
        }

        return view('App\Modules\Department\form', ['title' => 'Edit Department', 'navActive' => 'departments', 'department' => $department]);
    }

    public function update(int $id)
    {
        $rules = $this->departmentModel->getValidationRules();
        $rules['name'] = "required|min_length[2]|max_length[100]|is_unique[departments.name,id,{$id}]";

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->departmentModel->update($id, [
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
        ]);

        $this->logActivity('department', 'update', $id, 'Updated department #' . $id);

        return redirect()->to('/departments')->with('success', 'Department updated.');
    }

    public function delete(int $id)
    {
        if ($this->departmentModel->isInUse($id)) {
            return redirect()->to('/departments')->with('error', 'Cannot delete a department that still has tasks mapped to it.');
        }

        $this->departmentModel->delete($id);
        $this->logActivity('department', 'delete', $id, 'Deleted department #' . $id);

        return redirect()->to('/departments')->with('success', 'Department deleted.');
    }
}
