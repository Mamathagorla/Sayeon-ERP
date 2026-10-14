<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class PayrollRunModel extends Model
{
    protected $table         = 'payroll_runs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['company_id', 'month', 'year', 'status', 'processed_at', 'paid_at', 'created_by'];
    protected $useTimestamps = false;

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    /**
     * The run that already covers $companyId for this month: that company's own run, or a legacy org-wide run
     * (company_id NULL, from before payroll was per company) that contains one of its employees' payslips.
     */
    public function forPeriod(int $month, int $year, int $companyId): ?array
    {
        $own = $this->where(['month' => $month, 'year' => $year, 'company_id' => $companyId])->first();

        if ($own !== null) {
            return $own;
        }

        return $this->db->table('payroll_runs pr')->select('pr.*')
            ->join('payslips ps', 'ps.payroll_run_id = pr.id')
            ->join('employee_profiles ep', 'ep.user_id = ps.user_id')
            ->where('pr.month', $month)->where('pr.year', $year)->where('pr.company_id IS NULL', null, false)
            ->where('ep.company_id', $companyId)
            ->limit(1)->get()->getRowArray() ?: null;
    }

    /**
     * Runs a viewer may see. $companyScope null = unrestricted (Super Admin); otherwise the company's own runs plus any
     * legacy org-wide run that contains one of its payslips.
     */
    public function listAll(?int $companyScope = null): array
    {
        $builder = $this->orderBy('year', 'DESC')->orderBy('month', 'DESC');

        if ($companyScope !== null) {
            $scope = (int) $companyScope;
            $builder->groupStart()
                ->where('company_id', $scope)
                ->orGroupStart()
                    ->where('company_id IS NULL', null, false)
                    ->where('EXISTS (SELECT 1 FROM payslips ps JOIN employee_profiles ep ON ep.user_id = ps.user_id WHERE ps.payroll_run_id = payroll_runs.id AND ep.company_id = ' . $scope . ')', null, false)
                ->groupEnd()
                ->groupEnd();
        }

        return $builder->findAll();
    }

    public function isVisibleTo(array $run, ?int $companyScope): bool
    {
        if ($companyScope === null) {
            return true;
        }

        if ($run['company_id'] !== null) {
            return (int) $run['company_id'] === $companyScope;
        }

        return $this->db->table('payslips ps')->join('employee_profiles ep', 'ep.user_id = ps.user_id')
            ->where('ps.payroll_run_id', (int) $run['id'])->where('ep.company_id', $companyScope)->countAllResults() > 0;
    }
}
