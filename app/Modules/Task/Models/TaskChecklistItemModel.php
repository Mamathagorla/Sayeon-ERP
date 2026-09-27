<?php

namespace App\Modules\Task\Models;

use CodeIgniter\Model;

class TaskChecklistItemModel extends Model
{
    protected $table         = 'task_checklist_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['task_id', 'title', 'is_done', 'sort_order'];

    protected $validationRules = ['title' => 'required|min_length[1]|max_length[255]'];

    public function forTask(int $taskId): array
    {
        return $this->where('task_id', $taskId)->orderBy('sort_order', 'ASC')->findAll();
    }

    public function progressFor(int $taskId): array
    {
        $items = $this->forTask($taskId);
        $total = count($items);
        $done  = count(array_filter($items, static fn ($i) => (int) $i['is_done'] === 1));

        return ['total' => $total, 'done' => $done, 'percent' => $total > 0 ? (int) round($done / $total * 100) : 0];
    }
}
