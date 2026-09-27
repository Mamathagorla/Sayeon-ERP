<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class PayslipModel extends Model
{
    protected $table         = 'payslips';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['payroll_run_id', 'user_id', 'basic', 'hra', 'allowances', 'deductions', 'gross', 'net'];
    protected $useTimestamps = false;

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    /**
     * $companyScope (0 or a real id) narrows the run's payslips to one
     * company's employees — a payroll run itself spans every company
     * (see PayrollRunModel), so without this an HR Manager viewing a
     * run would see every other company's salary figures too. null
     * (Super Admin's "All Companies") keeps the full run.
     */
    public function forRun(int $payrollRunId, ?int $companyScope = null): array
    {
        $builder = $this->select('payslips.*, users.name as user_name, departments.name as department_name')
            ->join('users', 'users.id = payslips.user_id')
            ->join('employee_profiles', 'employee_profiles.user_id = payslips.user_id', 'left')
            ->join('departments', 'departments.id = employee_profiles.department_id', 'left')
            ->where('payroll_run_id', $payrollRunId);

        if ($companyScope !== null) {
            $builder->where('employee_profiles.company_id', $companyScope);
        }

        return $builder->orderBy('users.name', 'ASC')->findAll();
    }

    public function forUser(int $userId): array
    {
        return $this->select('payslips.*, payroll_runs.month, payroll_runs.year, payroll_runs.status as run_status')
            ->join('payroll_runs', 'payroll_runs.id = payslips.payroll_run_id')
            ->where('payslips.user_id', $userId)
            ->orderBy('payroll_runs.year', 'DESC')
            ->orderBy('payroll_runs.month', 'DESC')
            ->findAll();
    }

    /**
     * Everything the printable payslip document needs in one row —
     * employee_profiles/departments/companies are LEFT-joined (purely
     * additive) so a payslip for a user without a full profile still
     * returns, just with nulls for those columns.
     */
    public function withRelations(int $id): ?array
    {
        return $this->select('payslips.*, users.name as user_name, payroll_runs.month, payroll_runs.year, payroll_runs.status as run_status,
                employee_profiles.employee_code, employee_profiles.designation, employee_profiles.date_of_joining,
                departments.name as department_name,
                companies.name as company_name, companies.gst as company_gst, companies.registered_address as company_address')
            ->join('users', 'users.id = payslips.user_id')
            ->join('payroll_runs', 'payroll_runs.id = payslips.payroll_run_id')
            ->join('employee_profiles', 'employee_profiles.user_id = payslips.user_id', 'left')
            ->join('departments', 'departments.id = employee_profiles.department_id', 'left')
            ->join('companies', 'companies.id = employee_profiles.company_id', 'left')
            ->where('payslips.id', $id)
            ->first();
    }
}
