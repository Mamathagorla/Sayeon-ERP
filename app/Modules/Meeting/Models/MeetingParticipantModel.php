<?php

namespace App\Modules\Meeting\Models;

use CodeIgniter\Model;

class MeetingParticipantModel extends Model
{
    protected $table         = 'meeting_participants';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['meeting_id', 'user_id', 'external_name', 'external_email'];

    protected $validationRules = [
        'meeting_id'     => 'required|integer',
        'external_name'  => 'permit_empty|max_length[150]|regex_match[/^[\p{L}\s.\'-]+$/u]',
        'external_email' => 'permit_empty|valid_email',
    ];

    protected $validationMessages = [
        'external_name' => [
            'regex_match' => 'Name may only contain letters, spaces, apostrophes, hyphens and periods.',
        ],
        'external_email' => [
            'valid_email' => 'Enter a valid email address.',
        ],
    ];

    public function forMeeting(int $meetingId): array
    {
        return $this->select('meeting_participants.*, users.name as user_name')
            ->join('users', 'users.id = meeting_participants.user_id', 'left')
            ->where('meeting_id', $meetingId)
            ->findAll();
    }

    /**
     * Display label regardless of whether the participant is an internal
     * user or an external invitee — used by the show view and any future
     * notification fan-out.
     */
    public function displayName(array $participant): string
    {
        return $participant['user_name'] ?? $participant['external_name'] ?? $participant['external_email'] ?? 'Unknown';
    }
}
