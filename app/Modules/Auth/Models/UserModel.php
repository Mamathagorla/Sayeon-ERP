<?php

namespace App\Modules\Auth\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'name', 'email', 'phone', 'password_hash', 'role_id',
        'status', 'avatar_path', 'last_login_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'     => 'required|min_length[2]|max_length[150]|regex_match[/^[\p{L}\s.\'-]+$/u]',
        'email'    => 'required|valid_email|is_unique[users.email,id,{id}]',
        'phone'    => 'permit_empty|regex_match[/^[0-9]{10}$/]',
        'role_id'  => 'required|integer',
        'status'   => 'required|in_list[active,inactive]',
    ];

    protected $validationMessages = [
        'name' => [
            'regex_match' => 'Name may only contain letters, spaces, apostrophes, hyphens and periods.',
        ],
        'email' => [
            'is_unique' => 'A user with this email already exists.',
        ],
        'phone' => [
            'regex_match' => 'Phone number must be exactly 10 digits.',
        ],
    ];

    /**
     * Find an active user by email, with their role slug attached.
     */
    public function findActiveByEmail(string $email): ?array
    {
        return $this->select('users.*, roles.slug as role_slug, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.email', $email)
            ->where('users.status', 'active')
            ->first();
    }

    /**
     * All permission slugs granted to this user via their role.
     */
    public function permissionsFor(int $userId): array
    {
        $db = db_connect();

        $rows = $db->table('users')
            ->select('permissions.slug')
            ->join('role_permissions', 'role_permissions.role_id = users.role_id')
            ->join('permissions', 'permissions.id = role_permissions.permission_id')
            ->where('users.id', $userId)
            ->get()
            ->getResultArray();

        return array_column($rows, 'slug');
    }

    public function listForOptions(): array
    {
        return $this->select('id, name, email')->where('status', 'active')->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Same as listForOptions(), plus each user's company (via their
     * employee_profiles row, null if they don't have one) — lets a
     * form's "Assigned To" dropdown filter itself client-side to match
     * whatever company is currently selected, the same way project
     * dropdowns already do via ProjectModel::optionsForCompany().
     * LEFT JOIN so a user without a profile still appears (with a null
     * company_id) rather than vanishing from the list entirely.
     */
    public function listForOptionsWithCompany(): array
    {
        return $this->select('users.id, users.name, users.email, employee_profiles.company_id')
            ->join('employee_profiles', 'employee_profiles.user_id = users.id', 'left')
            ->where('users.status', 'active')
            ->orderBy('users.name', 'ASC')
            ->findAll();
    }

    /**
     * Every active user with their role slug/name attached — powers
     * the dev-only Switch Profile picker.
     */
    public function listActiveWithRole(): array
    {
        return $this->select('users.id, users.name, users.email, roles.slug as role_slug, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.status', 'active')
            ->orderBy('roles.id', 'ASC')
            ->findAll();
    }

    /**
     * Same shape as findActiveByEmail(), keyed by id — used by Switch
     * Profile to rebuild the session exactly as attemptLogin() would.
     */
    public function findActiveById(int $id): ?array
    {
        return $this->select('users.*, roles.slug as role_slug, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.id', $id)
            ->where('users.status', 'active')
            ->first();
    }
}
