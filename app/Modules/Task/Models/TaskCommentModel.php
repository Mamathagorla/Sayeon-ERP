<?php

namespace App\Modules\Task\Models;

use CodeIgniter\Model;

class TaskCommentModel extends Model
{
    protected $table          = 'task_comments';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $allowedFields  = ['task_id', 'user_id', 'comment'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = '';

    protected $validationRules = ['comment' => 'required|min_length[1]'];

    public function forTask(int $taskId): array
    {
        return $this->select('task_comments.*, users.name as user_name')
            ->join('users', 'users.id = task_comments.user_id')
            ->where('task_id', $taskId)
            ->orderBy('task_comments.created_at', 'ASC')
            ->findAll();
    }
}
