<?php

namespace App\Modules\Meeting\Models;

use CodeIgniter\Model;

/**
 * The standing attendee list for a recurring meeting template — every
 * generated occurrence gets this same list copied into
 * meeting_participants (see RecurringMeetingGenerator::runOne()).
 */
class RecurringMeetingParticipantModel extends Model
{
    protected $table         = 'recurring_meeting_participants';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['recurring_meeting_id', 'user_id'];
    protected $useTimestamps = false;

    public function forTemplate(int $recurringMeetingId): array
    {
        return $this->select('recurring_meeting_participants.*, users.name as user_name, users.email as user_email')
            ->join('users', 'users.id = recurring_meeting_participants.user_id')
            ->where('recurring_meeting_id', $recurringMeetingId)
            ->orderBy('users.name', 'ASC')
            ->findAll();
    }

    public function replaceForTemplate(int $recurringMeetingId, array $userIds): void
    {
        $this->where('recurring_meeting_id', $recurringMeetingId)->delete();

        $rows = [];
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId > 0) {
                $rows[] = ['recurring_meeting_id' => $recurringMeetingId, 'user_id' => $userId];
            }
        }

        if ($rows !== []) {
            $this->insertBatch($rows);
        }
    }
}
