<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class EmployeeIncrementLetterModel extends Model
{
    protected $table         = 'employee_increment_letters';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'employee_profile_id', 'company_id', 'previous_ctc', 'new_ctc',
        'effective_date', 'reason', 'issued_by',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'employee_profile_id' => 'required|integer',
        'company_id'          => 'required|integer',
        'new_ctc'              => 'required|decimal|greater_than[0]|less_than_equal_to[9999999999.99]',
        'effective_date'       => 'required|valid_date',
        'reason'               => 'permit_empty|max_length[255]',
    ];

    /**
     * History for one employee, most recent effective_date first — feeds
     * both the Employment tab's "Increment History" card and the letter
     * pages themselves.
     */
    public function forEmployee(int $employeeProfileId): array
    {
        return $this->where('employee_profile_id', $employeeProfileId)
            ->orderBy('effective_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function withRelations(int $id): ?array
    {
        return $this->select('employee_increment_letters.*, employee_profiles.employee_code, employee_profiles.designation,
                users.name as user_name, users.email as user_email,
                companies.name as company_name, companies.registered_address, companies.cin,
                issuer.name as issued_by_name')
            ->join('employee_profiles', 'employee_profiles.id = employee_increment_letters.employee_profile_id')
            ->join('users', 'users.id = employee_profiles.user_id')
            ->join('companies', 'companies.id = employee_increment_letters.company_id')
            ->join('users as issuer', 'issuer.id = employee_increment_letters.issued_by', 'left')
            ->where('employee_increment_letters.id', $id)
            ->first();
    }
}
