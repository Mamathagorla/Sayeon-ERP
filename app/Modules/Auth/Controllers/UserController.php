<?php

namespace App\Modules\Auth\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\RoleModel;
use App\Modules\Auth\Models\UserModel;

class UserController extends BaseController
{
    protected UserModel $userModel;
    protected RoleModel $roleModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->roleModel = new RoleModel();
    }

    public function index()
    {
        $users = $this->userModel
            ->select('users.*, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->orderBy('users.name', 'ASC')
            ->findAll();

        return view('App\Modules\Auth\users_index', [
            'title'     => 'Users',
            'navActive' => 'users',
            'users'     => $users,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Auth\user_form', [
            'title'     => 'Add User',
            'navActive' => 'users',
            'roles'     => $this->roleModel->optionsList(),
            'user'      => null,
        ]);
    }

    public function store()
    {
        $rules = [
            'name'     => 'required|min_length[2]|max_length[150]',
            'email'    => 'required|valid_email|is_unique[users.email]',
            'phone'    => 'permit_empty|regex_match[/^[0-9]{10}$/]',
            'role_id'  => 'required|integer',
            'password' => 'required|min_length[8]',
        ];
        $messages = [
            'phone' => ['regex_match' => 'Phone number must be exactly 10 digits.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = $this->userModel->insert([
            'name'          => $this->request->getPost('name'),
            'email'         => $this->request->getPost('email'),
            'phone'         => $this->request->getPost('phone'),
            'role_id'       => (int) $this->request->getPost('role_id'),
            'status'        => 'active',
            'password_hash' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
        ]);

        $this->logActivity('users', 'create', $id, 'Created user ' . $this->request->getPost('email'));

        return redirect()->to('/auth/users')->with('success', 'User created successfully.');
    }

    public function edit(int $id)
    {
        $user = $this->userModel->find($id);

        if ($user === null) {
            return redirect()->to('/auth/users')->with('error', 'User not found.');
        }

        return view('App\Modules\Auth\user_form', [
            'title'     => 'Edit User',
            'navActive' => 'users',
            'roles'     => $this->roleModel->optionsList(),
            'user'      => $user,
        ]);
    }

    public function update(int $id)
    {
        $rules = [
            'name'    => 'required|min_length[2]|max_length[150]',
            'email'   => "required|valid_email|is_unique[users.email,id,{$id}]",
            'phone'   => 'permit_empty|regex_match[/^[0-9]{10}$/]',
            'role_id' => 'required|integer',
            'status'  => 'required|in_list[active,inactive]',
        ];
        $messages = [
            'phone' => ['regex_match' => 'Phone number must be exactly 10 digits.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name'    => $this->request->getPost('name'),
            'email'   => $this->request->getPost('email'),
            'phone'   => $this->request->getPost('phone'),
            'role_id' => (int) $this->request->getPost('role_id'),
            'status'  => $this->request->getPost('status'),
        ];

        if ($this->request->getPost('password')) {
            $data['password_hash'] = password_hash($this->request->getPost('password'), PASSWORD_DEFAULT);
        }

        $this->userModel->update($id, $data);
        $this->logActivity('users', 'update', $id, 'Updated user #' . $id);

        return redirect()->to('/auth/users')->with('success', 'User updated successfully.');
    }

    public function delete(int $id)
    {
        if ($id === (int) session('userId')) {
            return redirect()->to('/auth/users')->with('error', 'You cannot delete your own account.');
        }

        $this->userModel->delete($id);
        $this->logActivity('users', 'delete', $id, 'Deactivated user #' . $id);

        return redirect()->to('/auth/users')->with('success', 'User removed.');
    }
}
