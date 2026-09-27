<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class LeaveTypeModel extends Model
{
    protected $table         = 'leave_types';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'annual_quota'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'name'         => 'required|min_length[2]|max_length[60]|is_unique[leave_types.name,id,{id}]',
        'annual_quota' => 'required|decimal|greater_than_equal_to[0]',
    ];

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    public function optionsList(): array
    {
        return $this->orderBy('name', 'ASC')->findAll();
    }

    public function isInUse(int $id): bool
    {
        return db_connect()->table('leave_requests')->where('leave_type_id', $id)->countAllResults() > 0;
    }
}
