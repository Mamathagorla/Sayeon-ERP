<?php

namespace App\Modules\Department\Models;

use CodeIgniter\Model;

class DepartmentModel extends Model
{
    protected $table            = 'departments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['name', 'slug', 'description'];
    protected $useTimestamps    = true;

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[100]|is_unique[departments.name,id,{id}]',
    ];

    protected $beforeInsert = ['generateSlug'];
    protected $beforeUpdate = ['generateSlug'];

    protected function generateSlug(array $data): array
    {
        if (! empty($data['data']['name'])) {
            $data['data']['slug'] = url_title($data['data']['name'], '-', true);
        }

        return $data;
    }

    public function optionsList(): array
    {
        return $this->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Whether this department has any tasks mapped to it — used to
     * block deletion of departments still in use.
     */
    public function isInUse(int $id): bool
    {
        return db_connect()->table('tasks')->where('department_id', $id)->countAllResults() > 0;
    }
}
