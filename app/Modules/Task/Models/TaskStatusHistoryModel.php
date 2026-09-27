<?php

namespace App\Modules\Task\Models;

use CodeIgniter\Model;

class TaskStatusHistoryModel extends Model
{
    protected $table         = 'task_status_history';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['task_id', 'from_status', 'to_status', 'changed_by', 'changed_at'];
    protected $useTimestamps = false;

    public function record(int $taskId, ?string $from, string $to, int $userId): void
    {
        $this->insert([
            'task_id'     => $taskId,
            'from_status' => $from,
            'to_status'   => $to,
            'changed_by'  => $userId,
            'changed_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function forTask(int $taskId): array
    {
        return $this->select('task_status_history.*, users.name as changed_by_name')
            ->join('users', 'users.id = task_status_history.changed_by')
            ->where('task_id', $taskId)
            ->orderBy('changed_at', 'DESC')
            ->findAll();
    }
}
