<?php

namespace App\Modules\Task\Models;

use CodeIgniter\Model;

class TaskAttachmentModel extends Model
{
    protected $table         = 'task_attachments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['task_id', 'file_path', 'original_name', 'file_size', 'uploaded_by'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public function forTask(int $taskId): array
    {
        return $this->select('task_attachments.*, users.name as uploaded_by_name')
            ->join('users', 'users.id = task_attachments.uploaded_by')
            ->where('task_id', $taskId)
            ->orderBy('task_attachments.created_at', 'DESC')
            ->findAll();
    }
}
