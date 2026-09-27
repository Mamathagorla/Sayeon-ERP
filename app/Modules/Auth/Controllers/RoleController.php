<?php

namespace App\Modules\Auth\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\PermissionModel;
use App\Modules\Auth\Models\RoleModel;

class RoleController extends BaseController
{
    protected RoleModel $roleModel;
    protected PermissionModel $permissionModel;

    public function __construct()
    {
        $this->roleModel       = new RoleModel();
        $this->permissionModel = new PermissionModel();
    }

    public function index()
    {
        return view('App\Modules\Auth\roles_index', [
            'title'     => 'Roles & Permissions',
            'navActive' => 'roles',
            'roles'     => $this->roleModel->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function edit(int $id)
    {
        $role = $this->roleModel->find($id);

        if ($role === null) {
            return redirect()->to('/auth/roles')->with('error', 'Role not found.');
        }

        return view('App\Modules\Auth\role_edit', [
            'title'              => 'Edit Role: ' . $role['name'],
            'navActive'          => 'roles',
            'role'               => $role,
            'groupedPermissions' => $this->permissionModel->groupedByModule(),
            'assignedIds'        => $this->roleModel->permissionIds($id),
        ]);
    }

    public function update(int $id)
    {
        $role = $this->roleModel->find($id);

        if ($role === null) {
            return redirect()->to('/auth/roles')->with('error', 'Role not found.');
        }

        if ($role['slug'] === 'super_admin') {
            return redirect()->to('/auth/roles')->with('error', 'Super Admin always has full access and cannot be edited.');
        }

        $permissionIds = $this->request->getPost('permissions') ?? [];
        $this->roleModel->syncPermissions($id, $permissionIds);
        $this->logActivity('roles', 'update', $id, 'Updated permissions for role ' . $role['name']);

        return redirect()->to('/auth/roles')->with('success', 'Permissions updated. Affected users must re-login to pick up changes.');
    }
}
