<?php

namespace App\Modules\Meeting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Meeting\Models\MeetingParticipantModel;
use App\Modules\Meeting\Services\MeetingInvitationService;
use App\Modules\Notification\Services\MailService;
use App\Modules\Notification\Services\NotificationService;

class ParticipantController extends BaseController
{
    protected MeetingParticipantModel $participantModel;
    protected MeetingModel $meetingModel;
    protected NotificationService $notificationService;
    protected MeetingInvitationService $invitations;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->participantModel = new MeetingParticipantModel();
        $this->meetingModel     = new MeetingModel();
        $this->notificationService = new NotificationService();
        $this->invitations      = new MeetingInvitationService();
        $this->userModel        = new UserModel();
    }

    public function store(int $meetingId)
    {
        $meeting = $this->meetingModel->find($meetingId);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        if (! $this->participantModel->mayAccess($meeting, (int) $this->currentUserId(), session('roleSlug'))) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $userId        = $this->request->getPost('user_id');
        $externalName  = $this->request->getPost('external_name');
        $externalEmail = $userId ? null : (trim((string) $this->request->getPost('external_email')) ?: null);

        if (! $userId && ! $externalName) {
            return redirect()->back()->with('error', 'Pick a user or enter an external participant name.');
        }

        // The "Add participant" user dropdown is already filtered to this
        // meeting's company (see MeetingController::show()), but that's a
        // convenience, not a guarantee — a direct POST with a different
        // company's user id must still be rejected here.
        if ($userId && ! $this->participantMatchesCompany((int) $userId, (int) $meeting['company_id'])) {
            return redirect()->back()->with('error', 'That user does not belong to this meeting\'s company.');
        }

        if ($userId && $this->participantModel->where('meeting_id', $meetingId)->where('user_id', (int) $userId)->countAllResults() > 0) {
            return redirect()->back()->with('error', 'That person is already a participant of this meeting.');
        }

        // The same external address is invited only once. A double submit would
        // otherwise add a second row and send a second invitation.
        if ($externalEmail !== null && $this->participantModel
            ->where('meeting_id', $meetingId)
            ->where('external_email', $externalEmail)
            ->countAllResults() > 0) {
            return redirect()->back()->with('error', 'That email address is already invited to this meeting.');
        }

        $inserted = $this->participantModel->insert([
            'meeting_id'     => $meetingId,
            'user_id'        => $userId ?: null,
            'external_name'  => $userId ? null : $externalName,
            'external_email' => $externalEmail,
        ]);

        if ($inserted === false) {
            return redirect()->back()->with('errors', $this->participantModel->errors());
        }

        $invite = $this->inviteNewParticipant($meeting, $userId ? (int) $userId : null, $externalName, $externalEmail);

        if ($userId && (int) $userId !== (int) $this->currentUserId()) {
            $message = 'You were added to "' . $meeting['title'] . '" on ' . $meeting['meeting_date'] . '.';
            if (! empty($meeting['meeting_link'])) {
                $message .= ' Join at: ' . $meeting['meeting_link'];
            }

            $this->notificationService->notify(
                (int) $userId,
                'meeting_invite',
                'Meeting invite: ' . $meeting['title'],
                $message,
                'meeting',
                $meetingId
            );
        }

        return redirect()->to('/meetings/' . $meetingId)->with('success', 'Participant added.' . $invite);
    }

    /**
     * Same rule as MeetingController::participantsMatchCompany(), for the
     * single user added here.
     */
    private function participantMatchesCompany(int $userId, int $companyId): bool
    {
        return db_connect()->table('employee_profiles')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->countAllResults() > 0;
    }

    /**
     * Emails the invitation to the one person just added, when the meeting
     * already has a join link. Returns a sentence for the flash message that
     * says what actually happened.
     */
    private function inviteNewParticipant(array $meeting, ?int $userId, ?string $externalName, ?string $externalEmail): string
    {
        $email = $externalEmail;
        $name  = $externalName;

        if ($userId !== null) {
            $user  = $this->userModel->find($userId);
            $email = $user['email'] ?? null;
            $name  = $user['name'] ?? null;
        }

        if ($email === null || trim((string) $email) === '') {
            return $userId === null ? ' No invitation was emailed because no email address was entered.' : ' No invitation was emailed: no email address on file.';
        }

        return match ($this->invitations->sendToParticipant($meeting, (string) $email, $name)) {
            MailService::SENT                 => ' Invitation emailed.',
            MeetingInvitationService::NO_LINK => ' No invitation was emailed because the meeting has no join link yet.',
            MailService::NOT_CONFIGURED       => ' No invitation was emailed: email delivery is not configured on this server.',
            MailService::INVALID              => ' No invitation was emailed: the email address is not valid.',
            default                           => ' The invitation email could not be sent (see the log).',
        };
    }

    public function delete(int $meetingId, int $participantId)
    {
        $meeting = $this->meetingModel->find($meetingId);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        if (! $this->participantModel->mayAccess($meeting, (int) $this->currentUserId(), session('roleSlug'))) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $participant = $this->participantModel->find($participantId);

        if ($participant && (int) $participant['meeting_id'] === $meetingId) {
            $this->participantModel->delete($participantId);
        }

        return redirect()->to('/meetings/' . $meetingId)->with('success', 'Participant removed.');
    }
}
