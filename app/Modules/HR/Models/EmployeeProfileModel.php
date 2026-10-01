<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class EmployeeProfileModel extends Model
{
    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'intern'];
    public const STATUSES = ['active', 'on_leave', 'resigned', 'terminated'];

    protected $table         = 'employee_profiles';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'user_id', 'company_id', 'department_id', 'employee_code', 'designation',
        'reporting_manager_id', 'employment_type', 'status', 'date_of_joining',
        'date_of_birth', 'address', 'emergency_contact_name', 'emergency_contact_phone',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'user_id'                 => 'required|integer|is_unique[employee_profiles.user_id,id,{id}]',
        'company_id'              => 'required|integer',
        // Letters/digits/hyphens — matches nextEmployeeCode()'s own
        // "EMP-0001" format, so a strict alpha_numeric rule (no hyphen)
        // would reject the app's own generated default.
        'employee_code'           => 'required|regex_match[/^[A-Za-z0-9-]+$/]|max_length[20]|is_unique[employee_profiles.employee_code,id,{id}]',
        'employment_type'         => 'required|in_list[full_time,part_time,contract,intern]',
        'status'                  => 'required|in_list[active,on_leave,resigned,terminated]',
        'date_of_birth'           => 'permit_empty|valid_date',
        'date_of_joining'         => 'permit_empty|valid_date',
        'emergency_contact_name'  => 'permit_empty|max_length[150]|regex_match[/^[\p{L}\s.\'-]+$/u]',
        'emergency_contact_phone' => 'permit_empty|regex_match[/^[0-9]{10}$/]',
    ];

    protected $validationMessages = [
        'employee_code' => [
            'regex_match' => 'Employee code may only contain letters, numbers and hyphens.',
        ],
        'emergency_contact_name' => [
            'regex_match' => 'Name may only contain letters, spaces, apostrophes, hyphens and periods.',
        ],
        'emergency_contact_phone' => [
            'regex_match' => 'Phone number must be exactly 10 digits.',
        ],
    ];

    /**
     * List with joined user/company/department/manager names, for the
     * Employees index table and the Company "Employees" tab.
     */
    public function filtered(array $filters = [])
    {
        $builder = $this->select('employee_profiles.*, users.name as user_name, users.email as user_email, users.phone as user_phone,
                users.avatar_path as user_avatar, users.status as account_status, users.last_login_at as user_last_login_at,
                companies.name as company_name, departments.name as department_name, manager.name as manager_name')
            ->join('users', 'users.id = employee_profiles.user_id')
            ->join('companies', 'companies.id = employee_profiles.company_id')
            ->join('departments', 'departments.id = employee_profiles.department_id', 'left')
            ->join('users as manager', 'manager.id = employee_profiles.reporting_manager_id', 'left');

        // array_key_exists (not empty()) — 0 means "Company Admin has no
        // employee_profiles company assigned yet" and must match zero
        // rows, not fall through to showing every company's employees.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('employee_profiles.company_id', $filters['company_id']);
        }
        if (! empty($filters['department_id'])) {
            $builder->where('employee_profiles.department_id', $filters['department_id']);
        }
        if (! empty($filters['status'])) {
            $builder->where('employee_profiles.status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $builder->like('users.name', $filters['q']);
        }

        return $builder->orderBy('users.name', 'ASC');
    }

    public function withRelations(int $id): ?array
    {
        return $this->filtered()->where('employee_profiles.id', $id)->first();
    }

    public function byUserId(int $userId): ?array
    {
        return $this->filtered()->where('employee_profiles.user_id', $userId)->first();
    }

    /**
     * Looked up for Attendance's CSV bulk-upload (see
     * AttendanceController::bulkUpload()) — each row identifies its
     * employee by their existing, already-unique employee_code rather
     * than a raw user_id, since that's what HR actually has on hand.
     */
    public function byEmployeeCode(string $code): ?array
    {
        return $this->filtered()->where('employee_profiles.employee_code', $code)->first();
    }

    /**
     * A suggested next employee_code — company-prefixed and short, e.g.
     * 'SAY001' for Sayeon's first employee (see companies.short_code,
     * reinstated by 2026-09-29-165959_ReaddShortCodeToCompanies for
     * exactly this). Falls back to a plain 'EMP-####' global sequence
     * only if this company has no short_code set yet — every company
     * seeded so far does. Purely a preview: derives the number by
     * scanning what's already saved rather than consuming a counter, so
     * loading the "Add Employee" form repeatedly never burns or skips
     * numbers — the field stays an editable text input, guarded by
     * employee_code's existing table-wide UNIQUE key.
     */
    public function nextEmployeeCode(int $companyId): string
    {
        $shortCode = company_short_code($companyId);

        if ($shortCode === null || $shortCode === '') {
            $last = $this->select('employee_code')->orderBy('id', 'DESC')->first();

            $nextNumber = 1;
            if ($last && preg_match('/(\d+)$/', $last['employee_code'], $m)) {
                $nextNumber = (int) $m[1] + 1;
            }

            return 'EMP-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $shortCode . str_pad((string) next_company_sequence_number('employee_profiles', 'employee_code', $companyId, $shortCode), 3, '0', STR_PAD_LEFT);
    }
}
