<?php

namespace App\Modules\Meeting\Services\Providers;

/**
 * One implementation per real video-conferencing provider (Google Meet,
 * Zoom) plus a NullMeetingProvider for provider = 'none'. MeetingController
 * only ever talks to this interface — it never branches on which
 * provider is active, and never fabricates a join URL itself: a provider
 * that isn't configured or whose API call fails returns
 * MeetingProviderResult::fail(...), and the meeting is not saved with a
 * fake link (see MeetingController::store()/update()).
 */
interface MeetingProviderInterface
{
    /**
     * @param array{title: string, agenda: ?string, meeting_date: string, start_time: ?string, end_time: ?string} $meeting
     * @param string[] $participantEmails
     */
    public function createMeeting(array $meeting, array $participantEmails): MeetingProviderResult;

    /**
     * Updates the SAME external meeting identified by $providerMeetingId
     * — never creates a second one. Called only when the organizer edits
     * a meeting that already has a provider meeting on this same
     * provider (see MeetingController::update()'s duplicate-prevention
     * logic).
     *
     * @param array{title: string, agenda: ?string, meeting_date: string, start_time: ?string, end_time: ?string} $meeting
     * @param string[] $participantEmails
     */
    public function updateMeeting(string $providerMeetingId, array $meeting, array $participantEmails): MeetingProviderResult;

    /**
     * Cancels/deletes the external meeting — called when the organizer
     * switches a meeting away from this provider (to 'none' or to a
     * different provider), so an abandoned meeting doesn't linger on the
     * provider's side. Best-effort: a failure here is logged but never
     * blocks the ERP-side change the organizer actually asked for.
     */
    public function deleteMeeting(string $providerMeetingId): bool;

    /**
     * Whether this provider has the configuration (API/OAuth
     * credentials) it needs to actually make calls — checked before
     * every create/update so an unconfigured provider fails fast with a
     * clear message instead of attempting a doomed HTTP call.
     */
    public function isConfigured(): bool;
}
