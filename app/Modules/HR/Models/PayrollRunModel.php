<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class PayrollRunModel extends Model
{
    protected $table         = 'payroll_runs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['month', 'year', 'status', 'processed_at', 'created_by'];
    protected $useTimestamps = false;

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    public function forPeriod(int $month, int $year): ?array
    {
        return $this->where('month', $month)->where('year', $year)->first();
    }

    public function listAll(): array
    {
        return $this->orderBy('year', 'DESC')->orderBy('month', 'DESC')->findAll();
    }
}
