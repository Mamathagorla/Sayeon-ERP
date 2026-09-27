<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class SalaryStructureModel extends Model
{
    protected $table         = 'salary_structures';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'basic', 'hra', 'allowances', 'deductions', 'effective_from'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Upper bound matches the DECIMAL(12,2) column — the field can't
    // physically hold more than this, so reject it before the DB does.
    private const MAX_AMOUNT = 9999999999.99;

    protected $validationRules = [
        'basic'          => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[' . self::MAX_AMOUNT . ']',
        'hra'            => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[' . self::MAX_AMOUNT . ']',
        'allowances'     => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[' . self::MAX_AMOUNT . ']',
        'deductions'     => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[' . self::MAX_AMOUNT . ']',
        'effective_from' => 'required|valid_date',
    ];

    public function byUserId(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first();
    }

    /**
     * Every user who has both an employee profile and a salary
     * structure — the set a payroll run can actually be generated for.
     * $companyScope (0 or a real id) narrows this to one company's
     * employees — an HR Manager (or Company Admin) generating payroll
     * must only pay their own company's staff, not the whole org's.
     * null (Super Admin's "All Companies") keeps the org-wide set.
     */
    public function payableUsers(?int $companyScope = null): array
    {
        $builder = $this->select('salary_structures.*, users.name as user_name')
            ->join('users', 'users.id = salary_structures.user_id')
            ->join('employee_profiles', 'employee_profiles.user_id = salary_structures.user_id')
            ->where('employee_profiles.status', 'active');

        if ($companyScope !== null) {
            $builder->where('employee_profiles.company_id', $companyScope);
        }

        return $builder->findAll();
    }
}
