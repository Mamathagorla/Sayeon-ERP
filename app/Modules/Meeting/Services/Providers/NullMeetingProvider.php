<?php

namespace App\Modules\Meeting\Services\Providers;

/**
 * provider = 'none' — the organizer typed (or left blank) meeting_link
 * manually, same as the app's pre-existing behaviour before this feature.
 * Always "succeeds" with no provider id/url of its own, so
 * MeetingController's create/update flow needs no special-case branch
 * for "no provider selected".
 */
class NullMeetingProvider implements MeetingProviderInterface
{
    public function createMeeting(array $meeting, array $participantEmails): MeetingProviderResult
    {
        return MeetingProviderResult::ok(null, null);
    }

    public function updateMeeting(string $providerMeetingId, array $meeting, array $participantEmails): MeetingProviderResult
    {
        return MeetingProviderResult::ok(null, null);
    }

    public function deleteMeeting(string $providerMeetingId): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return true;
    }
}
