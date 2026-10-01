<?php

namespace App\Modules\Meeting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Department\Models\DepartmentModel;
use App\Modules\Meeting\Models\GoogleOAuthConnectionModel;
use App\Modules\Meeting\Models\MeetingActionItemModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Meeting\Models\MeetingParticipantModel;
use App\Modules\Meeting\Services\MeetingProviderFactory;
use App\Modules\Meeting\Services\Providers\MeetingProviderResult;
use App\Modules\Notification\Services\NotificationService;

class MeetingController extends BaseController
{
    protected MeetingModel $meetingModel;
    protected MeetingParticipantModel $participantModel;
    protected MeetingActionItemModel $actionItemModel;
    protected CompanyModel $companyModel;
    protected UserModel $userModel;
    protected DepartmentModel $departmentModel;
    protected NotificationService $notificationService;
    protected GoogleOAuthConnectionModel $googleConnectionModel;

    public function __construct()
    {
        $this->meetingModel     = new MeetingModel();
        $this->participantModel = new MeetingParticipantModel();
        $this->actionItemModel  = new MeetingActionItemModel();
        $this->companyModel     = new CompanyModel();
        $this->userModel        = new UserModel();
        $this->departmentModel  = new DepartmentModel();
        $this->notificationService = new NotificationService();
        $this->googleConnectionModel = new GoogleOAuthConnectionModel();
    }

    public function index()
    {
        $filters = $this->request->getGet(['company_id', 'upcoming']) ?? [];

        // Employee sees only meetings they're a participant in, same
        // personal-scope treatment as Tasks and the Dashboard.
        $isPersonalScope = session('roleSlug') === 'employee';
        if ($isPersonalScope) {
            $filters['participant_user_id'] = session('userId');
        }

        $filters = array_filter($filters);

        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        if ($companyScope !== null) {
            $filters['company_id'] = $companyScope;
        }

        return view('App\Modules\Meeting\index', [
            'title'           => $isPersonalScope ? 'My Meetings' : 'Meetings',
            'navActive'       => 'meetings',
            'isPersonalScope' => $isPersonalScope,
            'meetings'        => $this->meetingModel->filtered($filters)->findAll(),
            'companies'       => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'filters'         => $filters,
        ]);
    }

    public function create()
    {
        return view('App\Modules\Meeting\form', [
            'title'     => 'Schedule Meeting',
            'navActive' => 'meetings',
            'meeting'   => null,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'users'     => $this->scopedUserOptions($this->userModel->listForOptions()),
            'selectedParticipantIds' => [],
            'googleConnection' => $this->googleConnectionModel->forUser((int) $this->currentUserId()),
        ]);
    }

    public function store()
    {
        if (! $this->validate($this->meetingModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only schedule meetings for your own company.');
        }

        // Organizer picks attendees right on this same form — every
        // selected internal user is added as a participant immediately
        // and invited in the same step, rather than needing a second
        // trip to the show page's separate "add participant" form. The
        // organizer themselves is always included too (they're attending
        // their own meeting), so it shows up in their own "My Meetings"
        // list even if they forgot to tick their own name.
        $participantIds   = array_map('intval', $this->request->getPost('participant_user_ids') ?? []);
        $participantIds[] = $this->currentUserId();
        $participantIds   = array_unique($participantIds);

        $provider = $this->request->getPost('provider') ?: 'none';

        // Real meeting creation happens BEFORE the ERP row is inserted —
        // if the provider call fails, nothing is saved at all, so there
        // is never a half-created meeting with a fake/missing link (see
        // requirement: "Do NOT generate a fake/internal ERP meeting
        // URL" / "Do not send the link if provider meeting creation
        // failed", applied here to creation itself too).
        $providerResult = null;
        if ($provider !== 'none') {
            $providerResult = MeetingProviderFactory::make($provider)->createMeeting(
                $this->providerMeetingInput(),
                $this->participantEmails($participantIds)
            );

            if (! $providerResult->success) {
                return redirect()->back()->withInput()->with('error', 'Could not create the ' . $this->providerLabel($provider) . ' meeting: ' . $providerResult->errorMessage);
            }
        }

        $id = $this->meetingModel->insert($this->meetingPayload(true, $provider, $providerResult));
        $this->logActivity('meeting', 'create', $id, 'Scheduled meeting: ' . $this->request->getPost('title'));

        $added = $this->participantModel->bulkAdd($id, $participantIds);

        $meeting = $this->meetingModel->find($id);
        $emailed = $this->notifyParticipants($added, $meeting, 'meeting_invite', 'Meeting invite: ' . $meeting['title'], $this->inviteMessage($meeting));

        $message = 'Meeting scheduled and ' . count($added) . ' participant(s) invited.';
        $message .= $emailed > 0
            ? " Meeting details emailed to {$emailed} participant(s)."
            : ' Email delivery is not configured on this server — the join link is available on this page and in each participant\'s notifications for manual sharing.';

        return redirect()->to('/meetings/' . $id)->with('success', $message);
    }

    /**
     * Same personal-scope treatment as index()'s filter, but for a
     * single meeting: a plain 'employee' may only open a meeting they
     * organized or were actually invited to — being able to browse to
     * any numeric /meetings/{id} in their own company (the only check
     * that existed before) let them see every colleague's meeting
     * details and join link regardless of invitation. Staff roles keep
     * their existing full company-scoped visibility (oversight is the
     * point of those roles).
     */
    public function show(int $id)
    {
        $meeting = $this->meetingModel->withCompany($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        if (! $this->canAccessMeeting($meeting)) {
            return redirect()->to('/meetings')->with('error', 'You are not invited to this meeting.');
        }

        return view('App\Modules\Meeting\show', [
            'title'        => $meeting['title'],
            'navActive'    => 'meetings',
            'meeting'      => $meeting,
            'joinUrl'      => $this->meetingModel->joinUrl($meeting),
            'participants' => $this->participantModel->forMeeting($id),
            'participantModel' => $this->participantModel,
            'actionItems'  => $this->actionItemModel->forMeeting($id),
            'users'        => $this->scopedUserOptions($this->userModel->listForOptions()),
            'departments'  => $this->departmentModel->optionsList(),
        ]);
    }

    public function edit(int $id)
    {
        $meeting = $this->meetingModel->find($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        return view('App\Modules\Meeting\form', [
            'title'     => 'Edit Meeting',
            'navActive' => 'meetings',
            'meeting'   => $meeting,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'users'     => $this->scopedUserOptions($this->userModel->listForOptions()),
            'selectedParticipantIds' => $this->participantModel->internalUserIdsFor($id),
            'googleConnection' => $this->googleConnectionModel->forUser((int) $this->currentUserId()),
        ]);
    }

    public function update(int $id)
    {
        $meeting = $this->meetingModel->find($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        if (! $this->validate($this->meetingModel->getValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'You can only assign meetings to your own company.');
        }

        $newLink = $this->request->getPost('meeting_link') ?: null;

        // "when" for the reschedule check below — compared against what
        // was actually submitted, not just the link, since the ask is
        // specifically "editing the meeting time ... automatically
        // update/resend notifications".
        $rescheduled = $this->request->getPost('meeting_date') !== $meeting['meeting_date']
            || ($this->request->getPost('start_time') ?: null) !== $meeting['start_time']
            || ($this->request->getPost('end_time') ?: null) !== $meeting['end_time']
            || $this->request->getPost('location') !== $meeting['location'];

        $submittedIds = array_map('intval', $this->request->getPost('participant_user_ids') ?? []);

        $oldProvider           = $meeting['provider'] ?? 'none';
        $newProvider           = $this->request->getPost('provider') ?: 'none';
        $oldProviderMeetingId  = $meeting['provider_meeting_id'] ?? null;

        $providerWarning = null;
        // Default: nothing provider-related changes. meeting_link only
        // follows the posted (manually-typed) value when there is no
        // real provider managing this meeting — a provider-managed link
        // is never organizer-editable free text, so it only ever
        // changes via a successful provider create/update result below.
        $providerFields = [
            'provider'            => $meeting['provider'],
            'provider_meeting_id' => $meeting['provider_meeting_id'],
            'meeting_link'        => $oldProvider === 'none' ? $newLink : $meeting['meeting_link'],
        ];

        if ($newProvider === $oldProvider && $newProvider !== 'none' && $oldProviderMeetingId) {
            // SAME provider, already has an external meeting — update
            // that SAME meeting (never create a second one), and only
            // bother calling out to the provider at all when something
            // it actually cares about changed.
            if ($rescheduled) {
                $result = MeetingProviderFactory::make($newProvider)->updateMeeting(
                    $oldProviderMeetingId,
                    $this->providerMeetingInput(),
                    $this->participantEmails($submittedIds)
                );

                if ($result->success) {
                    $providerFields['meeting_link'] = $result->joinUrl ?? $meeting['meeting_link'];
                } else {
                    // Per "update the existing provider meeting where
                    // supported, or update only the ERP record when
                    // appropriate" — a provider-side failure doesn't
                    // block the organizer's ERP-side edit, it just
                    // means the two may now be out of sync; surfaced
                    // as a warning, not a hard stop.
                    $providerWarning = 'Could not update the ' . $this->providerLabel($newProvider) . ' meeting (' . $result->errorMessage . ') — the ERP record was still updated.';
                }
            }
        } elseif ($newProvider !== $oldProvider) {
            // Switching provider (including to/from 'none') always means
            // a genuinely different external meeting — create the new
            // one FIRST, and only once that succeeds delete the old one,
            // so a failed switch never leaves the organizer with zero
            // working meetings.
            if ($newProvider !== 'none') {
                $result = MeetingProviderFactory::make($newProvider)->createMeeting(
                    $this->providerMeetingInput(),
                    $this->participantEmails($submittedIds)
                );

                if (! $result->success) {
                    return redirect()->back()->withInput()->with('error', 'Could not create the ' . $this->providerLabel($newProvider) . ' meeting: ' . $result->errorMessage);
                }

                $providerFields = ['provider' => $newProvider, 'provider_meeting_id' => $result->providerMeetingId, 'meeting_link' => $result->joinUrl];
            } else {
                $providerFields = ['provider' => 'none', 'provider_meeting_id' => null, 'meeting_link' => $newLink];
            }

            if ($oldProvider !== 'none' && $oldProviderMeetingId) {
                MeetingProviderFactory::make($oldProvider)->deleteMeeting($oldProviderMeetingId);
            }
        }

        $linkChanged = $providerFields['meeting_link'] !== $meeting['meeting_link'];

        $this->meetingModel->update($id, $this->meetingPayload(false, null, null, $providerFields));
        $this->logActivity('meeting', 'update', $id, 'Updated meeting #' . $id);

        // Participant diff — add newly-checked users (with an invite),
        // remove unchecked ones. External participants (added
        // separately, no user_id) are untouched by this form.
        $existingIds = $this->participantModel->internalUserIdsFor($id);
        $toRemove    = array_diff($existingIds, $submittedIds);

        $this->participantModel->removeUsers($id, $toRemove);
        $added = $this->participantModel->bulkAdd($id, $submittedIds);

        $updated = $this->meetingModel->find($id);
        $emailed = 0;

        if ($added !== []) {
            $emailed += $this->notifyParticipants($added, $updated, 'meeting_invite', 'Meeting invite: ' . $updated['title'], $this->inviteMessage($updated));
        }

        // A newly added/changed link is exactly what attendees need to
        // know about — re-notify everyone already on the participant
        // list.
        if ($linkChanged && $updated['meeting_link'] !== null) {
            $this->notifyParticipantsOfLink($id, $updated['title'], $updated['meeting_link']);
        }

        // Reschedule notice — goes to everyone still on the list after
        // the diff above (a participant who was also just removed in
        // this same edit doesn't need to hear the new meeting no longer
        // concerns them is also at a new time).
        if ($rescheduled) {
            $remaining = array_map(static fn (array $p) => (int) $p['user_id'], array_filter(
                $this->participantModel->forMeeting($id),
                static fn (array $p) => ! empty($p['user_id'])
            ));
            $emailed += $this->notifyParticipants(
                $remaining,
                $updated,
                'meeting_rescheduled',
                'Meeting rescheduled: ' . $updated['title'],
                $this->inviteMessage($updated, true)
            );
        }

        $message = 'Meeting updated.';
        if ($emailed > 0) {
            $message .= " Updated details emailed to {$emailed} participant(s).";
        }
        if ($providerWarning) {
            $message .= ' ' . $providerWarning;
        }

        return redirect()->to('/meetings/' . $id)->with('success', $message);
    }

    /**
     * The auto-generated join link's actual destination. Same access
     * check as show() (participant-or-staff), plus the token itself has
     * to match — belt-and-braces: a leaked numeric id alone still isn't
     * enough to get in, and a valid token for the wrong meeting id
     * (impossible today since tokens are unique, but checked explicitly
     * anyway) doesn't work either. Redirects straight to the organizer's
     * external meeting_link (Zoom/Teams/etc.) when one is set — that's
     * the actual call; when none is set yet, falls back to a minimal
     * details page instead of a dead end.
     */
    public function join(int $id, string $token)
    {
        $meeting = $this->meetingModel->withCompany($id);

        if ($meeting === null || ! hash_equals($meeting['join_token'] ?? '', $token)) {
            return redirect()->to('/meetings')->with('error', 'This meeting link is invalid.');
        }

        if ($this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id']) || ! $this->canAccessMeeting($meeting)) {
            return redirect()->to('/meetings')->with('error', 'You are not invited to this meeting.');
        }

        if (! empty($meeting['meeting_link'])) {
            return redirect()->to($meeting['meeting_link']);
        }

        return view('App\Modules\Meeting\join', [
            'title'   => 'Join: ' . $meeting['title'],
            'meeting' => $meeting,
        ]);
    }

    public function delete(int $id)
    {
        $meeting = $this->meetingModel->find($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $this->meetingModel->delete($id);
        $this->logActivity('meeting', 'delete', $id, 'Deleted meeting #' . $id);

        return redirect()->to('/meetings')->with('success', 'Meeting deleted.');
    }

    public function updateMom(int $id)
    {
        $meeting = $this->meetingModel->find($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        $this->meetingModel->update($id, ['mom' => $this->request->getPost('mom')]);
        $this->logActivity('meeting', 'update', $id, 'Updated minutes of meeting for #' . $id);

        return redirect()->to('/meetings/' . $id)->with('success', 'Minutes of Meeting saved.');
    }

    /**
     * @param MeetingProviderResult|null $providerResult Only passed by store() — a fresh provider create result to seed provider/provider_meeting_id/meeting_link from.
     * @param array{provider: string, provider_meeting_id: ?string, meeting_link: ?string}|null $providerFields Only passed by update() — the already-resolved provider fields for this save (see update()'s branching above).
     */
    private function meetingPayload(bool $isNew, ?string $provider = null, ?MeetingProviderResult $providerResult = null, ?array $providerFields = null): array
    {
        $data = [
            'company_id'   => (int) $this->request->getPost('company_id'),
            'title'        => $this->request->getPost('title'),
            'agenda'       => $this->request->getPost('agenda'),
            'meeting_date' => $this->request->getPost('meeting_date'),
            'start_time'   => $this->request->getPost('start_time') ?: null,
            'end_time'     => $this->request->getPost('end_time') ?: null,
            'location'     => $this->request->getPost('location'),
        ];

        if ($providerFields !== null) {
            $data += $providerFields;
        } else {
            $provider ??= 'none';
            $data['provider']            = $provider;
            $data['provider_meeting_id'] = $providerResult?->providerMeetingId;
            $data['meeting_link']        = $provider === 'none'
                ? ($this->request->getPost('meeting_link') ?: null)
                : $providerResult?->joinUrl;
        }

        if ($isNew) {
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }

    /**
     * @return array{title: string, agenda: ?string, meeting_date: string, start_time: ?string, end_time: ?string}
     */
    private function providerMeetingInput(): array
    {
        return [
            'title'        => (string) $this->request->getPost('title'),
            'agenda'       => $this->request->getPost('agenda') ?: null,
            'meeting_date' => (string) $this->request->getPost('meeting_date'),
            'start_time'   => $this->request->getPost('start_time') ?: null,
            'end_time'     => $this->request->getPost('end_time') ?: null,
        ];
    }

    /**
     * @param int[] $userIds
     * @return string[]
     */
    private function participantEmails(array $userIds): array
    {
        $userIds = array_filter(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }

        return array_values(array_filter(array_column(
            $this->userModel->whereIn('id', $userIds)->findAll(),
            'email'
        )));
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'google_meet' => 'Google Meet',
            'zoom'        => 'Zoom',
            default       => $provider,
        };
    }

    /**
     * Notifies every internal participant (skipping the actor, same as
     * ParticipantController::store()'s existing invite notification)
     * that a meeting link is available/changed — in-app, plus a real
     * email attempt (see attemptEmail()) since this one, unlike the
     * invite notification, is specifically "the link changed, here it
     * is again". External (email-only) invitees have no in-app account
     * to notify — share the link with them manually.
     */
    private function notifyParticipantsOfLink(int $meetingId, string $title, string $link): void
    {
        $actorId = (int) $this->currentUserId();

        foreach ($this->participantModel->forMeeting($meetingId) as $p) {
            if (empty($p['user_id']) || (int) $p['user_id'] === $actorId) {
                continue;
            }

            $this->notificationService->notify(
                (int) $p['user_id'],
                'meeting_link',
                'Meeting link: ' . $title,
                'Join at: ' . $link,
                'meeting',
                $meetingId
            );

            if (! empty($p['user_email'])) {
                $this->attemptEmail($p['user_email'], 'Meeting link: ' . $title, 'Join at: ' . $link);
            }
        }
    }

    /**
     * Whether the current viewer may open this meeting's page/join link.
     * Staff roles (anyone company-scoped-or-wider besides plain
     * 'employee') keep full visibility into their company's meetings —
     * that oversight is the point of those roles, same as Attendance/
     * Leave/Payroll elsewhere. A plain employee must be the organizer or
     * an actual invited participant; outOfScope() already confirmed
     * same-company before this runs, so this only narrows further.
     */
    private function canAccessMeeting(array $meeting): bool
    {
        if (session('roleSlug') !== 'employee') {
            return true;
        }

        $userId = (int) $this->currentUserId();

        if ((int) $meeting['created_by'] === $userId) {
            return true;
        }

        foreach ($this->participantModel->forMeeting((int) $meeting['id']) as $p) {
            if ((int) ($p['user_id'] ?? 0) === $userId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fan-out notify helper shared by store()/update() — skips the
     * acting user (same as the pre-existing single-add notify in
     * ParticipantController::store()) and anyone with no real account.
     * Always writes the in-app notification (the one channel that always
     * works in this app); also attempts a real email per recipient when
     * they have an address on file, and returns how many of those
     * emails actually went out, so the caller can report accurately
     * rather than ever claiming delivery that didn't happen.
     */
    private function notifyParticipants(array $userIds, array $meeting, string $type, string $subject, string $message): int
    {
        $actorId = (int) $this->currentUserId();
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id) => $id > 0 && $id !== $actorId)));

        if ($userIds === []) {
            return 0;
        }

        $emailBody = $this->inviteEmailBody($meeting, $message);
        $emails    = array_column($this->userModel->whereIn('id', $userIds)->findAll(), 'email', 'id');

        $emailed = 0;
        foreach ($userIds as $userId) {
            $this->notificationService->notify($userId, $type, $subject, $message, 'meeting', (int) $meeting['id']);

            if (! empty($emails[$userId]) && $this->attemptEmail($emails[$userId], $subject, $emailBody)) {
                $emailed++;
            }
        }

        return $emailed;
    }

    /**
     * The invite/reschedule notification's short in-app body — includes
     * the auto-generated join link unconditionally (every meeting has
     * one, see MeetingModel::joinUrl(), which itself redirects straight
     * to the real provider link once one exists) plus the raw external
     * link too when set, so an invitee always has somewhere to click
     * regardless of which one the organizer filled in.
     */
    private function inviteMessage(array $meeting, bool $rescheduled = false): string
    {
        $lead = $rescheduled
            ? 'This meeting has been rescheduled: "' . esc($meeting['title']) . '"'
            : 'You have been invited to "' . esc($meeting['title']) . '"';

        $when = esc($meeting['meeting_date'])
            . ($meeting['start_time'] ? ' from ' . date('g:i A', strtotime($meeting['start_time'])) : '')
            . ($meeting['end_time'] ? ' to ' . date('g:i A', strtotime($meeting['end_time'])) : '');

        $providerNote = ($meeting['provider'] ?? 'none') !== 'none' ? ' via ' . $this->providerLabel($meeting['provider']) : '';

        $message = $lead . ' on ' . $when . $providerNote . '. Join at: ' . $this->meetingModel->joinUrl($meeting);

        if (! empty($meeting['meeting_link'])) {
            $message .= ' (direct link: ' . $meeting['meeting_link'] . ')';
        }

        return $message;
    }

    /**
     * The fuller, multi-line email body — same facts the in-app message
     * carries, laid out per the requirement's explicit field list
     * (title, date, start/end time, provider, real join link) rather
     * than the terse one-liner the notification bell shows. $summary is
     * the already-built in-app message, reused as the closing line
     * rather than duplicated.
     */
    private function inviteEmailBody(array $meeting, string $summary): string
    {
        $lines = [
            'Meeting: ' . $meeting['title'],
            'Date: ' . date('d M Y', strtotime($meeting['meeting_date'])),
            'Start time: ' . ($meeting['start_time'] ? date('g:i A', strtotime($meeting['start_time'])) : '—'),
            'End time: ' . ($meeting['end_time'] ? date('g:i A', strtotime($meeting['end_time'])) : '—'),
            'Provider: ' . (($meeting['provider'] ?? 'none') !== 'none' ? $this->providerLabel($meeting['provider']) : 'Not set'),
        ];

        if (! empty($meeting['agenda'])) {
            $lines[] = 'Agenda: ' . $meeting['agenda'];
        }

        // The REAL join link when one exists (provider-returned or
        // manually entered) — falls back to the access-controlled
        // internal link (which itself redirects to the real one once
        // set) so there's always something to click either way.
        $lines[] = 'Join link: ' . (! empty($meeting['meeting_link']) ? $meeting['meeting_link'] : $this->meetingModel->joinUrl($meeting));

        return implode("\n", $lines);
    }

    /**
     * Attempts a real send via CI4's own Email service — only when SMTP
     * is actually configured (this app's .env ships email.SMTPHost but
     * deliberately blank email.SMTPUser/SMTPPass, so a connect attempt
     * with no credentials would otherwise just hang the request on a
     * doomed handshake). Returns false on any failure so callers never
     * claim delivery that didn't happen and can fall back to the
     * in-app notification / manually-shared link instead.
     */
    private function attemptEmail(string $to, string $subject, string $message): bool
    {
        $config = config('Email');

        if (empty($config->SMTPHost) || empty($config->SMTPUser) || empty($config->SMTPPass) || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $email = \Config\Services::email();
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage(nl2br(esc($message)));

            return $email->send();
        } catch (\Throwable $e) {
            log_message('error', 'Meeting notification email failed: ' . $e->getMessage());

            return false;
        }
    }
}
