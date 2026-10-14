<?php

namespace App\Modules\Meeting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Auth\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;
use App\Modules\Company\Models\CompanyModel;
use App\Modules\Department\Models\DepartmentModel;
use App\Modules\Meeting\Models\GoogleOAuthConnectionModel;
use App\Modules\Meeting\Models\MeetingActionItemModel;
use App\Modules\Meeting\Models\MeetingModel;
use App\Modules\Meeting\Models\MeetingParticipantModel;
use App\Modules\Meeting\Services\MeetingInvitationService;
use App\Modules\Meeting\Services\MeetingProviderFactory;
use App\Modules\Meeting\Services\Providers\MeetingProviderResult;
use App\Modules\Notification\Services\MailService;
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
    protected MeetingInvitationService $invitations;
    protected MailService $mail;

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
        $this->invitations      = new MeetingInvitationService();
        $this->mail             = new MailService();
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
            'entryTimezone' => $this->entryTimezone(),
            'serverTimezone' => config('App')->appTimezone,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            // listForOptionsWithCompany() (not plain listForOptions()) — each
            // row carries company_id, so the view can filter the participant
            // checklist client-side as the Company field changes.
            'users'     => $this->scopedUserOptions($this->userModel->listForOptionsWithCompany()),
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

        if ($timeError = $this->timeError($provider)) {
            return redirect()->back()->withInput()->with('error', $timeError);
        }

        $when = $this->scheduleWhen($provider);

        if ($attendeeError = $this->attendeeError($participantIds, $provider)) {
            return redirect()->back()->withInput()->with('error', $attendeeError);
        }

        // The organizer is always in $participantIds regardless of their own
        // company (e.g. a Super Admin with no employee_profiles row at all) —
        // excluded here since outOfScope() above is what actually authorizes
        // them to create a meeting for this company; it's the invited
        // participants who must belong to it.
        $postedCompanyId = (int) $this->request->getPost('company_id');
        if (! $this->participantsMatchCompany(array_diff($participantIds, [(int) $this->currentUserId()]), $postedCompanyId)) {
            return redirect()->back()->withInput()->with('error', 'All participants must belong to the selected company.');
        }

        // A retry of a submission that already succeeded (double click, browser
        // resubmit after a slow response) opens the existing meeting instead of
        // creating a second provider event.
        if ($provider !== 'none') {
            $existing = $this->meetingModel->recentDuplicate((int) $this->currentUserId(), (int) $postedCompanyId, (string) $this->request->getPost('title'), $when, $provider);
            if ($existing !== null) {
                return redirect()->to('/meetings/' . $existing['id'])->with('success', 'This meeting was already scheduled a moment ago, so no duplicate was created.');
            }
        }

        // Real meeting creation happens BEFORE the ERP row is inserted —
        // if the provider call fails, nothing is saved at all, so there
        // is never a half-created meeting with a fake/missing link (see
        // requirement: "Do NOT generate a fake/internal ERP meeting
        // URL" / "Do not send the link if provider meeting creation
        // failed", applied here to creation itself too).
        $providerResult = null;
        if ($provider !== 'none') {
            $providerResult = MeetingProviderFactory::make($provider)->createMeeting(
                $this->providerMeetingInput($when),
                $this->participantEmails($participantIds)
            );
            if (! $providerResult->success) {
                return redirect()->back()->withInput()->with('error', 'Could not create the ' . $this->providerLabel($provider) . ' meeting: ' . $providerResult->errorMessage);
            }
        }

        $id = $this->meetingModel->insert($this->meetingPayload(true, $when, $provider, $providerResult));
        $this->logActivity('meeting', 'create', $id, 'Scheduled meeting: ' . $this->request->getPost('title'));

        $added = $this->participantModel->bulkAdd($id, $participantIds);

        $meeting = $this->meetingModel->find($id);
        // In-app notifications only here; the email goes out once, below, to
        // every participant with an address (see MeetingInvitationService).
        $this->notifyParticipants($added, $meeting, 'meeting_invite', 'Meeting invite: ' . $meeting['title'], $this->inviteMessage($meeting), false);

        // The meeting and its provider event already exist at this point, so an
        // email problem is reported as a note, never as a failed creation.
        try {
            $invite = $this->invitations->sendForMeeting($meeting, $this->invitations->recipientsForMeeting($id));
        } catch (\Throwable $e) {
            log_message('error', 'Meeting invitation emails failed: ' . $e->getMessage());
            $invite = ['status' => 'attempted', 'sent' => 0, 'failed' => count($added)];
        }

        $message = 'Meeting scheduled and ' . count($added) . ' participant(s) invited.' . $this->inviteNote($invite);

        return redirect()->to('/meetings/' . $id)->with('success', $message);
    }

    /**
     * One sentence about the invitation emails, for the flash message. Never
     * claims a delivery that did not happen.
     *
     * @param array{status: string, sent: int, failed: int} $invite
     */
    private function inviteNote(array $invite): string
    {
        return match ($invite['status']) {
            'attempted' => $invite['failed'] > 0
                ? " Invitation emailed to {$invite['sent']} participant(s); {$invite['failed']} could not be sent (see the log)."
                : " Invitation emailed to {$invite['sent']} participant(s).",
            MeetingInvitationService::NO_LINK => ' No invitation was emailed because the meeting has no join link.',
            MailService::NOT_CONFIGURED => ' Email delivery is not configured on this server, so no invitation was emailed. The join link is on this page for manual sharing.',
            default => '',
        };
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
            // Filtered to THIS meeting's company specifically, not the viewer's
            // own scope — a Super Admin browsing "All Companies" (or a
            // different company) must still only be offered this meeting's
            // own company's people to add as a participant.
            'users'        => $this->scopedUserOptions($this->userModel->listForOptions(), (int) $meeting['company_id']),
            'departments'  => $this->departmentModel->optionsList(),
            'projects'     => (new \App\Modules\Task\Models\ProjectModel())->optionsForCompany((int) $meeting['company_id']),
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
            'entryTimezone' => $this->entryTimezone($meeting),
            'serverTimezone' => config('App')->appTimezone,
            'companies' => $this->scopedCompanyOptions($this->companyModel->optionsList()),
            'users'     => $this->scopedUserOptions($this->userModel->listForOptionsWithCompany()),
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

        $newProvider = $this->request->getPost('provider') ?: 'none';

        if ($timeError = $this->timeError($newProvider, $meeting)) {
            return redirect()->back()->withInput()->with('error', $timeError);
        }

        $when = $this->scheduleWhen($newProvider, $meeting);

        $oldProvider          = $meeting['provider'] ?? 'none';
        $oldProviderMeetingId = $meeting['provider_meeting_id'] ?? null;

        if ($attendeeError = $this->attendeeError($submittedIds, $newProvider)) {
            return redirect()->back()->withInput()->with('error', $attendeeError);
        }

        // Same organizer exemption as store() — the viewer may be re-saving a
        // meeting they already organize/attend without an employee_profiles
        // row of their own (e.g. Super Admin); it's the other participants
        // that must belong to the company.
        if (! $this->participantsMatchCompany(array_diff($submittedIds, [(int) $this->currentUserId()]), (int) $this->request->getPost('company_id'))) {
            return redirect()->back()->withInput()->with('error', 'All participants must belong to the selected company.');
        }

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
            'sync_status'         => $meeting['sync_status'] ?? 'not_synced',
            'sync_error'          => $meeting['sync_error'] ?? null,
        ];

        if ($newProvider !== 'none' && $newProvider === $oldProvider && $oldProviderMeetingId) {
            // Same calendar provider with an existing external event: PATCH
            // that event on every save, so title, time, agenda and attendees
            // stay in sync. The event id never changes, so no duplicate is made.
            $result = MeetingProviderFactory::make($newProvider)->updateMeeting(
                $oldProviderMeetingId,
                $this->providerMeetingInput($when),
                $this->participantEmails($submittedIds)
            );

            if ($result->success) {
                $providerFields['sync_status'] = 'synced';
                $providerFields['sync_error']  = null;
            } else {
                // The ERP edit is still saved. The calendar event may be
                // out of date until it syncs; the organizer is told.
                $providerFields['sync_status'] = 'sync_failed';
                $providerFields['sync_error']  = $result->errorMessage;
                $providerWarning = 'The ' . $this->providerLabel($newProvider) . ' event could not be updated (' . $result->errorMessage . '). The meeting was saved here, but the calendar event is out of date.';
            }
        } elseif ($newProvider !== 'none' && ($newProvider !== $oldProvider || ! $oldProviderMeetingId)) {
            // A different provider, or a provider with no event yet: create the
            // new event FIRST. Only after that succeeds is the old event
            // cancelled, so a failed switch never leaves the organizer without
            // a working meeting.
            $result = MeetingProviderFactory::make($newProvider)->createMeeting(
                $this->providerMeetingInput($when),
                $this->participantEmails($submittedIds)
            );

            if (! $result->success) {
                return redirect()->back()->withInput()->with('error', 'Could not create the ' . $this->providerLabel($newProvider) . ' meeting: ' . $result->errorMessage);
            }

            $providerFields = [
                'provider'            => $newProvider,
                'provider_meeting_id' => $result->providerMeetingId,
                'meeting_link'        => $result->joinUrl,
                'sync_status'         => 'synced',
                'sync_error'          => null,
            ];

            if ($oldProvider !== 'none' && $oldProviderMeetingId && ! MeetingProviderFactory::make($oldProvider)->deleteMeeting($oldProviderMeetingId)) {
                $providerWarning = 'The previous ' . $this->providerLabel($oldProvider) . ' event could not be cancelled automatically — remove it from that calendar.';
            }
        } elseif ($newProvider === 'none' && $oldProvider !== 'none') {
            // Online meeting turned off: cancel the calendar event, then store
            // the manually typed link only.
            if ($oldProviderMeetingId && ! MeetingProviderFactory::make($oldProvider)->deleteMeeting($oldProviderMeetingId)) {
                $providerWarning = 'The ' . $this->providerLabel($oldProvider) . ' event could not be cancelled automatically — remove it from that calendar.';
            }

            $providerFields = [
                'provider'            => 'none',
                'provider_meeting_id' => null,
                'meeting_link'        => $newLink,
                'sync_status'         => 'not_synced',
                'sync_error'          => null,
            ];
        }

        $linkChanged = $providerFields['meeting_link'] !== $meeting['meeting_link'];

        $this->meetingModel->update($id, $this->meetingPayload(false, $when, null, null, $providerFields));
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
     * external meeting_link (Zoom, Google Meet, etc.) when one is set — that's
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

        // Cancel the calendar event first. If that fails the ERP record is kept,
        // so there is never a live event without its record.
        $provider = $meeting['provider'] ?? 'none';
        if ($provider !== 'none' && ! empty($meeting['provider_meeting_id'])) {
            $cancelled = MeetingProviderFactory::make($provider)->deleteMeeting($meeting['provider_meeting_id']);

            if (! $cancelled) {
                return redirect()->to('/meetings/' . $id)->with('error', 'The ' . $this->providerLabel($provider) . ' event could not be cancelled, so the meeting was kept. Reconnect your account and try again.');
            }
        }

        $this->meetingModel->delete($id);
        $this->logActivity('meeting', 'delete', $id, 'Deleted meeting #' . $id);

        return redirect()->to('/meetings')->with('success', 'Meeting deleted' . ($provider !== 'none' ? ' and its calendar event was cancelled.' : '.'));
    }

    public function updateMom(int $id)
    {
        $meeting = $this->meetingModel->find($id);

        if ($meeting === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $meeting['company_id'])) {
            return redirect()->to('/meetings')->with('error', 'Meeting not found.');
        }

        if (! $this->participantModel->mayAccess($meeting, (int) $this->currentUserId(), session('roleSlug'))) {
            return redirect()->to('/meetings')->with('error', 'You are not invited to this meeting.');
        }

        $this->meetingModel->update($id, ['mom' => $this->request->getPost('mom')]);
        $this->logActivity('meeting', 'update', $id, 'Updated minutes of meeting for #' . $id);

        return redirect()->to('/meetings/' . $id)->with('success', 'Minutes of Meeting saved.');
    }

    /**
     * @param MeetingProviderResult|null $providerResult Only passed by store() — a fresh provider create result to seed provider/provider_meeting_id/meeting_link from.
     * @param array{provider: string, provider_meeting_id: ?string, meeting_link: ?string}|null $providerFields Only passed by update() — the already-resolved provider fields for this save (see update()'s branching above).
     */
    private function meetingPayload(bool $isNew, array $when, ?string $provider = null, ?MeetingProviderResult $providerResult = null, ?array $providerFields = null): array
    {
        $data = [
            'company_id'   => (int) $this->request->getPost('company_id'),
            'title'        => $this->request->getPost('title'),
            'agenda'       => $this->request->getPost('agenda'),
            'meeting_date' => $when['meeting_date'],
            'start_time'   => $when['start_time'],
            'end_time'     => $when['end_time'],
            'location'     => $this->request->getPost('location'),
            'end_date'     => $when['end_date'],
            'timezone'     => $when['timezone'],
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
            $data['sync_status']         = $provider === 'none' ? 'not_synced' : 'synced';
            $data['sync_error']          = null;
        }

        if ($isNew) {
            $data['created_by'] = $this->currentUserId();
        }

        return $data;
    }

    /**
     * @param array{meeting_date: string, start_time: ?string, end_time: ?string, end_date: ?string, timezone: string} $when
     * @return array{title: string, agenda: ?string, meeting_date: string, start_time: ?string, end_time: ?string, end_date: ?string, timezone: string}
     */
    private function providerMeetingInput(array $when): array
    {
        return [
            'title'        => (string) $this->request->getPost('title'),
            'agenda'       => $this->request->getPost('agenda') ?: null,
            'meeting_date' => $when['meeting_date'],
            'end_date'     => $when['end_date'],
            'start_time'   => $when['start_time'],
            'end_time'     => $when['end_time'],
            'timezone'     => $when['timezone'],
            'request_key'  => $this->requestKey($when),
        ];
    }

    /**
     * Identifies one submission of the meeting form, so a retry of it can be
     * recognised by a provider that has no idempotency key of its own (Zoom).
     * It is the form's own token when posted (the form re-sends it on a retry
     * after an error); otherwise a hash of the organizer, the page and the
     * schedule, which is the same for a resubmission of identical content.
     */
    private function requestKey(array $when): string
    {
        $token = (string) $this->request->getPost('submission_token');
        $basis = $token !== ''
            ? 'token|' . $token
            : implode('|', [$this->request->getUri()->getPath(), $this->request->getPost('title'), $this->request->getPost('company_id'), $when['meeting_date'], $when['start_time'], $when['end_time']]);

        return substr(hash('sha256', (int) $this->currentUserId() . '|' . $basis), 0, 16);
    }

    /**
     * The timezone the posted wall-clock date and times are in. A new meeting
     * is entered in the organization timezone. An existing meeting keeps the
     * zone it was saved in; rows from before timezones were stored have NULL
     * and were saved in the server timezone.
     */
    private function entryTimezone(?array $meeting = null): string
    {
        if ($meeting === null) {
            return erp_org_timezone();
        }

        return $meeting['timezone'] ?: config('App')->appTimezone;
    }

    /**
     * Where a meeting is stored and sent to its provider. Zoom schedules in the
     * server timezone (ZoomProvider sends date_default_timezone_get() and is
     * left unchanged), so a Zoom meeting is converted to that zone before it is
     * saved. A converted end time that lands on another day is reported by
     * timeError(), because Zoom works out the duration from the time strings.
     *
     * @return array{meeting_date: string, start_time: ?string, end_time: ?string, end_date: ?string, timezone: string}
     */
    private function scheduleWhen(string $provider, ?array $meeting = null): array
    {
        $date   = (string) $this->request->getPost('meeting_date');
        $start  = $this->request->getPost('start_time') ?: null;
        $end    = $this->request->getPost('end_time') ?: null;
        $entry  = $this->entryTimezone($meeting);
        $server = config('App')->appTimezone;

        $when = [
            'meeting_date' => $date,
            'start_time'   => $start,
            'end_time'     => $end,
            'end_date'     => null,
            'timezone'     => $entry,
        ];

        if ($provider !== 'zoom') {
            return $when;
        }

        $when['timezone'] = $server;

        if ($start === null || $entry === $server) {
            return $when;
        }

        $from    = new DateTimeZone($entry);
        $to      = new DateTimeZone($server);
        $startAt = (new DateTimeImmutable($date . ' ' . $start, $from))->setTimezone($to);
        $endAt   = $end !== null ? (new DateTimeImmutable($date . ' ' . $end, $from))->setTimezone($to) : null;

        $when['meeting_date'] = $startAt->format('Y-m-d');
        $when['start_time']   = $startAt->format('H:i:s');
        $when['end_time']     = $endAt?->format('H:i:s');

        $endDay = $endAt?->format('Y-m-d');
        if ($endDay !== null && $endDay !== $when['meeting_date']) {
            $when['end_date'] = $endDay;
        }

        return $when;
    }

    /**
     * One rule for every meeting: when both times are given the end must
     * fall strictly after the start, on the same day (overnight meetings are
     * only created from recurring templates, which tick "Ends the next day").
     * For Zoom the day is checked again in the server timezone the event is
     * scheduled in, because a conversion can move the end to another day.
     */
    private function timeError(string $provider, ?array $meeting = null): ?string
    {
        $start = $this->request->getPost('start_time') ?: null;
        $end   = $this->request->getPost('end_time') ?: null;

        if ($start === null || $end === null) {
            return null;
        }

        $date = (string) $this->request->getPost('meeting_date');

        if (strtotime($date . ' ' . $end) <= strtotime($date . ' ' . $start)) {
            return 'End time must be after start time. Check the 12 AM / 12 PM setting on both times.';
        }

        if ($provider === 'zoom' && $this->scheduleWhen($provider, $meeting)['end_date'] !== null) {
            return 'Zoom meetings are scheduled in ' . config('App')->appTimezone . ', and these times fall on different days there. Choose times that stay on one day in that timezone.';
        }

        return null;
    }

    /**
     * Every internal participant must belong to the meeting's own company —
     * the same rule, for the same reason, as TaskController's
     * assigneeMatchesCompany(): the Company field's dropdown-filtering JS is
     * a convenience, not a guarantee, and a scoped viewer can never even
     * reach another company's meeting to begin with (outOfScope() blocks
     * that earlier) — this specifically guards the Super Admin / unscoped
     * case, who could otherwise post a cross-company user id directly.
     * External participants (no user_id) aren't a company-membership
     * concern and are added separately, so they're never part of $userIds.
     *
     * @param int[] $userIds
     */
    private function participantsMatchCompany(array $userIds, int $companyId): bool
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if ($userIds === []) {
            return true;
        }

        $validCount = db_connect()->table('employee_profiles')
            ->select('user_id')
            ->where('company_id', $companyId)
            ->whereIn('user_id', $userIds)
            ->countAllResults();

        return $validCount === count($userIds);
    }

    /**
     * Attendees on a calendar event need a real email address; a missing or
     * malformed address is reported with the person's name, before any call
     * is made to the calendar provider.
     *
     * @param int[] $userIds
     */
    private function attendeeError(array $userIds, string $provider): ?string
    {
        if ($provider === 'none') {
            return null;
        }

        $userIds = array_values(array_filter(array_unique(array_map('intval', $userIds))));
        if ($userIds === []) {
            return null;
        }

        $problems = [];
        foreach ($this->userModel->whereIn('id', $userIds)->findAll() as $user) {
            if (empty($user['email'])) {
                $problems[] = $user['name'] . ' has no email address';
            } elseif (! filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                $problems[] = $user['name'] . ' has an invalid email address (' . $user['email'] . ')';
            }
        }

        return $problems === []
            ? null
            : 'Cannot invite these attendees to the calendar event: ' . implode('; ', $problems) . '.';
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
            'google_meet'   => 'Google Meet',
            'zoom'          => 'Zoom',
            default         => $provider,
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
    private function notifyParticipants(array $userIds, array $meeting, string $type, string $subject, string $message, bool $sendEmail = true): int
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

            if ($sendEmail && ! empty($emails[$userId]) && $this->attemptEmail($emails[$userId], $subject, $emailBody)) {
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
        return $this->mail->send($to, $subject, nl2br(esc($message)), $message) === MailService::SENT;
    }
}
