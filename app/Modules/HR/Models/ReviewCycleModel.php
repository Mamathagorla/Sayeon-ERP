<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class ReviewCycleModel extends Model
{
    protected $table         = 'review_cycles';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'start_date', 'end_date', 'status'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'name'       => 'required|min_length[2]|max_length[100]',
        'start_date' => 'required|valid_date',
        'end_date'   => 'required|valid_date',
    ];

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    public function optionsList(): array
    {
        return $this->orderBy('start_date', 'DESC')->findAll();
    }
}
