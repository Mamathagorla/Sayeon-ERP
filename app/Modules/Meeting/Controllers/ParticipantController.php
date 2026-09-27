<?php

namespace App\Modules\Meeting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Meeting\Models\MeetingParticipantModel;
use App\Modules\Notification\Services\NotificationService;

class ParticipantController extends BaseController
{
    protected MeetingParticipantModel $participantModel;
    protected MeetingModel $meetingModel;
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->participantModel = new MeetingParticipantModel();
        $this->meetingModel     = new MeetingModel();
        $this->notificationService = new NotificationService();
    }

    public function store(int $meetingId)
    {
        $meeting = $this->meetingModel->find($meetingId);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $userId       = $this->request->getPost('user_id');
        $externalName = $this->request->getPost('external_name');

        if (! $userId && ! $externalName) {
            return redirect()->back()->with('error', 'Pick a user or enter an external participant name.');
        }

        $inserted = $this->participantModel->insert([
            'meeting_id'     => $meetingId,
            'user_id'        => $userId ?: null,
            'external_name'  => $userId ? null : $externalName,
            'external_email' => $userId ? null : $this->request->getPost('external_email'),
        ]);

        if ($inserted === false) {
            return redirect()->back()->with('errors', $this->participantModel->errors());
        }

        if ($userId && (int) $userId !== (int) $this->currentUserId()) {
            $this->notificationService->notify(
                (int) $userId,
                'meeting_invite',
                'Meeting invite: ' . $meeting['title'],
                'You were added to "' . $meeting['title'] . '" on ' . $meeting['meeting_date'] . '.',
                'meeting',
                $meetingId
            );
        }

        return redirect()->to('/meetings/' . $meetingId)->with('success', 'Participant added.');
    }

    public function delete(int $meetingId, int $participantId)
    {
        $meeting = $this->meetingModel->find($meetingId);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $participant = $this->participantModel->find($participantId);

        if ($participant && (int) $participant['meeting_id'] === $meetingId) {
            $this->participantModel->delete($participantId);
        }

        return redirect()->to('/meetings/' . $meetingId)->with('success', 'Participant removed.');
    }
}
