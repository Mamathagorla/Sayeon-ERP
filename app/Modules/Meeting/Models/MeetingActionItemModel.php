<?php

namespace App\Modules\Meeting\Models;

use CodeIgniter\Model;

class MeetingActionItemModel extends Model
{
    protected $table         = 'meeting_action_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'meeting_id', 'description', 'assigned_to', 'due_date', 'status', 'linked_task_id',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'description' => 'required|min_length[2]|max_length[500]',
    ];

    public function forMeeting(int $meetingId): array
    {
        return $this->select('meeting_action_items.*, users.name as assignee_name')
            ->join('users', 'users.id = meeting_action_items.assigned_to', 'left')
            ->where('meeting_id', $meetingId)
            ->orderBy('meeting_action_items.due_date', 'ASC')
            ->findAll();
    }
}
