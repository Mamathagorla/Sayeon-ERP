<?php

namespace App\Modules\Expense\Models;

use CodeIgniter\Model;

class ExpenseCategoryModel extends Model
{
    public const STATUSES = ['active', 'inactive'];

    protected $table         = 'expense_categories';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'status'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'name'   => 'required|min_length[2]|max_length[100]|is_unique[expense_categories.name,id,{id}]',
        'status' => 'permit_empty|in_list[active,inactive]',
    ];

    protected $beforeInsert = ['stampCreatedAt'];

    protected function stampCreatedAt(array $data): array
    {
        $data['data']['created_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    public function optionsList(): array
    {
        return $this->where('status', 'active')->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Active categories, plus $currentName itself if it's inactive or
     * was never a seeded category at all (older free-text data from
     * before this table existed) — so opening the edit form for an
     * expense never silently drops or swaps its existing category just
     * because the list moved on since that expense was created.
     */
    public function optionsListIncluding(?string $currentName): array
    {
        $options = $this->optionsList();

        if ($currentName === null || $currentName === '') {
            return $options;
        }

        foreach ($options as $o) {
            if ($o['name'] === $currentName) {
                return $options;
            }
        }

        $options[] = ['id' => null, 'name' => $currentName, 'status' => 'inactive'];

        return $options;
    }
}
