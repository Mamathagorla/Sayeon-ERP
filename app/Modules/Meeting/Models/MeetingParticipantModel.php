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
        return $this->select('meeting_participants.*, users.name as user_name, users.email as user_email')
            ->join('users', 'users.id = meeting_participants.user_id', 'left')
            ->where('meeting_id', $meetingId)
            ->findAll();
    }

    /**
     * The email to show/notify for a participant row regardless of
     * whether they're an internal user (users.email) or an external
     * invitee (meeting_participants.external_email) — same "whichever
     * applies" pattern as displayName() below.
     */
    public function displayEmail(array $participant): ?string
    {
        return $participant['user_email'] ?? $participant['external_email'] ?? null;
    }

    /**
     * The internal (has a real user_id) participants already on a
     * meeting — used to diff against a newly-submitted participant list
     * on create/update, so callers know who's new (needs an invite) vs
     * who's unchanged. External invitees (no user_id) are a separate
     * concern, added/removed one at a time via ParticipantController.
     */
    public function internalUserIdsFor(int $meetingId): array
    {
        return array_map('intval', array_column(
            $this->select('user_id')->where('meeting_id', $meetingId)->where('user_id IS NOT NULL')->findAll(),
            'user_id'
        ));
    }

    /**
     * Adds every id in $userIds that isn't already on the meeting —
     * silently skips ones that are (the uniq_meeting_participant key
     * would reject them anyway; checking first avoids insertBatch()
     * aborting the whole batch over one duplicate). Returns the ids
     * actually inserted, so the caller knows exactly who's newly invited
     * and needs a notification.
     */
    public function bulkAdd(int $meetingId, array $userIds): array
    {
        $userIds  = array_unique(array_filter(array_map('intval', $userIds)));
        $existing = $this->internalUserIdsFor($meetingId);
        $toAdd    = array_values(array_diff($userIds, $existing));

        if ($toAdd === []) {
            return [];
        }

        $this->insertBatch(array_map(
            static fn (int $uid) => ['meeting_id' => $meetingId, 'user_id' => $uid],
            $toAdd
        ));

        return $toAdd;
    }

    /**
     * Removes every internal participant row in $userIds — the other
     * half of the create/update diff (see bulkAdd()).
     */
    public function removeUsers(int $meetingId, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $this->where('meeting_id', $meetingId)->whereIn('user_id', array_map('intval', $userIds))->delete();
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
