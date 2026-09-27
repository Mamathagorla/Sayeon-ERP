<?php

namespace App\Modules\Auth\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;

class AuthController extends BaseController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/');
        }

        return view('App\Modules\Auth\login');
    }

    public function attemptLogin()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $user = $this->userModel->findActiveByEmail($email);

        if ($user === null || ! password_verify($password, $user['password_hash'])) {
            // Deliberately generic message â€” do not reveal whether the email exists.
            return redirect()->back()->withInput()->with('error', 'Invalid email or password.');
        }

        $this->establishSession($user);

        $this->userModel->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        log_message('info', 'User {email} logged in.', ['email' => $email]);

        $redirectUrl = session()->getFlashdata('redirectUrl') ?? '/';

        return redirect()->to($redirectUrl);
    }

    private function establishSession(array $user): void
    {
        session()->set([
            'isLoggedIn'  => true,
            'userId'      => $user['id'],
            'userName'    => $user['name'],
            'userEmail'   => $user['email'],
            'roleId'      => $user['role_id'],
            'roleSlug'    => $user['role_slug'],
            'roleName'    => $user['role_name'],
            'permissions' => $this->userModel->permissionsFor((int) $user['id']),
            'avatarPath'  => $user['avatar_path'] ?? null,
        ]);

        $this->establishActiveCompany($user);
    }

    /**
     * Seeds session('active_company_id') — the source BaseController::
     * companyScopeFor() reads first. Only Super Admin starts at the
     * 'all' sentinel ("All Companies") and can switch between companies
     * via CompanyController::switchActive() (also now restricted to
     * super_admin — see that method).
     *
     * Company Admin deliberately does NOT get this key set here — that
     * leaves companyScopeFor() to fall through to its own
     * employee_profiles lookup (same path every other role already
     * uses), pinning them to the one company their account belongs to.
     * Company data must never leak across companies to a Company Admin,
     * so this is enforced at the session-seeding level, not just hidden
     * in the UI.
     */
    private function establishActiveCompany(array $user): void
    {
        if ($user['role_slug'] === 'super_admin') {
            session()->set('active_company_id', self::ACTIVE_COMPANY_ALL);
        }
    }

    /**
     * Dev-only account switcher — swaps the session to another active
     * user without re-authenticating, so RBAC across roles can be
     * exercised without repeated logout/login. Hard-gated to the
     * development environment AND Super Admin — environment alone isn't
     * enough, since any role could otherwise instantly impersonate any
     * other user (including Super Admin) just by hitting this URL.
     * Never available outside either condition regardless of whether
     * the nav link happens to be shown.
     */
    public function switchProfile()
    {
        if (ENVIRONMENT !== 'development' || session('roleSlug') !== 'super_admin') {
            return redirect()->to('/')->with('error', 'Switch Profile is only available to Super Admin in the development environment.');
        }

        return view('App\Modules\Auth\switch_profile', [
            'title'     => 'Switch Profile',
            'navActive' => '',
            'users'     => $this->userModel->listActiveWithRole(),
        ]);
    }

    public function doSwitchProfile(int $userId)
    {
        if (ENVIRONMENT !== 'development' || session('roleSlug') !== 'super_admin') {
            return redirect()->to('/')->with('error', 'Switch Profile is only available to Super Admin in the development environment.');
        }

        $target = $this->userModel->findActiveById($userId);

        if ($target === null) {
            return redirect()->to('/auth/switch-profile')->with('error', 'That user is not available to switch to.');
        }

        $fromEmail = session()->get('userEmail');

        $this->establishSession($target);

        log_message('info', 'Switched profile from {from} to {to} (dev only).', [
            'from' => $fromEmail ?? 'guest',
            'to'   => $target['email'],
        ]);

        return redirect()->to('/');
    }

    public function logout()
    {
        $email = session()->get('userEmail');
        session()->destroy();

        log_message('info', 'User {email} logged out.', ['email' => $email]);

        return redirect()->to('/auth/login');
    }

    public function profile()
    {
        $user = $this->userModel->find(session()->get('userId'));

        return view('App\Modules\Auth\profile', ['user' => $user]);
    }

    public function updateProfile()
    {
        $userId = session()->get('userId');

        $rules = [
            'name'   => 'required|min_length[2]|max_length[150]',
            'phone'  => 'permit_empty|regex_match[/^[0-9]{10}$/]',
            'avatar' => 'permit_empty|is_image[avatar]|mime_in[avatar,image/jpg,image/jpeg,image/png,image/webp]|max_size[avatar,2048]',
        ];
        $messages = [
            'phone' => ['regex_match' => 'Phone number must be exactly 10 digits.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name'  => $this->request->getPost('name'),
            'phone' => $this->request->getPost('phone'),
        ];

        $avatar = $this->request->getFile('avatar');

        if ($avatar !== null && $avatar->isValid() && ! $avatar->hasMoved()) {
            $targetDir = FCPATH . 'uploads/avatars/' . $userId;

            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $oldAvatar = $this->userModel->find($userId)['avatar_path'] ?? null;

            $newName = $avatar->getRandomName();
            $avatar->move($targetDir, $newName);

            $data['avatar_path'] = 'uploads/avatars/' . $userId . '/' . $newName;

            if ($oldAvatar) {
                $oldFull = FCPATH . ltrim($oldAvatar, '/');
                if (is_file($oldFull)) {
                    unlink($oldFull);
                }
            }
        }

        $this->userModel->update($userId, $data);

        session()->set('userName', $this->request->getPost('name'));
        if (isset($data['avatar_path'])) {
            session()->set('avatarPath', $data['avatar_path']);
        }

        return redirect()->to('/auth/profile')->with('success', 'Profile updated.');
    }

    public function changePassword()
    {
        $userId = session()->get('userId');
        $user   = $this->userModel->find($userId);

        $rules = [
            'current_password' => 'required',
            'new_password'      => 'required|min_length[8]',
            'confirm_password'  => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        if (! password_verify($this->request->getPost('current_password'), $user['password_hash'])) {
            return redirect()->back()->with('error', 'Current password is incorrect.');
        }

        $this->userModel->update($userId, [
            'password_hash' => password_hash($this->request->getPost('new_password'), PASSWORD_DEFAULT),
        ]);

        return redirect()->to('/auth/profile')->with('success', 'Password changed successfully.');
    }
}
