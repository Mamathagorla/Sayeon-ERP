<?php

namespace App\Modules\Meeting\Services\Providers;

use Config\MeetingProviders;

/**
 * Creates a real Zoom meeting via Zoom's Server-to-Server OAuth app type
 * — the currently-recommended integration method for a backend service
 * acting as itself (Zoom retired the older JWT app type). $participantEmails
 * isn't used in the create/update call itself: Zoom's meeting-create API
 * has no "invite these emails" parameter (that's a separate Registrants
 * API, for registration-gated webinars/meetings, not a plain scheduled
 * meeting) — participants are informed via this app's own notification
 * system instead (see MeetingController::notifyParticipants()), which is
 * how a Zoom link is normally shared regardless of how it was created.
 */
class ZoomProvider implements MeetingProviderInterface
{
    use BoundedHttp;

    private const TOKEN_URL   = 'https://zoom.us/oauth/token';
    private const MEETINGS_API = 'https://api.zoom.us/v2/users/me/meetings';
    private const MEETING_API  = 'https://api.zoom.us/v2/meetings/';

    /** How long an uncertain create attempt is remembered for reconciliation, in seconds. */
    private const ATTEMPT_TTL = 900;

    private MeetingProviders $config;

    public function __construct()
    {
        $this->config = config('MeetingProviders');
    }

    public function isConfigured(): bool
    {
        return $this->config->zoomAccountId !== ''
            && $this->config->zoomClientId !== ''
            && $this->config->zoomClientSecret !== '';
    }

    public function createMeeting(array $meeting, array $participantEmails): MeetingProviderResult
    {
        if (! $this->isConfigured()) {
            return MeetingProviderResult::fail('Zoom is not configured on this server (missing Server-to-Server OAuth app credentials).');
        }

        try {
            $token = $this->accessToken();
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Could not authenticate with Zoom: ' . $e->getMessage());
        }

        // Zoom's create call has no idempotency key, so a request that timed out
        // may or may not have created a meeting. Each submission carries a
        // reference ("request_key") that is written into the meeting's agenda.
        // While an earlier attempt with the same reference is remembered as
        // uncertain, Zoom's own meeting list is searched for that reference
        // BEFORE anything is created, and a match is adopted instead of
        // duplicated.
        $key = isset($meeting['request_key']) && $meeting['request_key'] !== '' ? (string) $meeting['request_key'] : null;

        if ($key !== null && cache($this->attemptCacheKey($key)) !== null) {
            $found = $this->findByReference($token, $key);

            if ($found === null) {
                return MeetingProviderResult::fail('An earlier attempt to create this Zoom meeting did not finish, and Zoom could not be checked to see whether it was created (the Zoom app needs the "View and manage all user meetings" / list-meetings scope for that check). To avoid a duplicate, check your Zoom account, or wait ' . (int) (self::ATTEMPT_TTL / 60) . ' minutes and try again.');
            }

            if ($found !== false) {
                log_message('info', 'Zoom meeting from an earlier timed-out attempt was found and reused.');

                return MeetingProviderResult::ok((string) $found['id'], $found['join_url']);
            }
        }

        if ($key !== null) {
            cache()->save($this->attemptCacheKey($key), time(), self::ATTEMPT_TTL);
        }

        try {
            $response = $this->http('post', self::MEETINGS_API, [
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => json_encode($this->meetingBody($meeting, $key)),
            ], 'zoom.create');
        } catch (\Throwable $e) {
            // No answer: the meeting may exist. The attempt stays remembered so
            // the retry reconciles instead of creating a second meeting.
            return MeetingProviderResult::fail('Zoom did not answer in time (' . $e->getMessage() . '). The meeting may still have been created. Press Schedule again: the system checks Zoom first and will not create a duplicate.');
        }

        // A clear client error (4xx) means nothing was created; forget the attempt.
        // A success is kept remembered until it expires, covering a failure while saving.
        if ($key !== null && $response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            cache()->delete($this->attemptCacheKey($key));
        }

        return $this->resultFromMeetingResponse($response);
    }

    private function attemptCacheKey(string $key): string
    {
        return 'zoom_attempt_' . preg_replace('/[^A-Za-z0-9]/', '', $key);
    }

    /**
     * Looks through the host's upcoming Zoom meetings for the one carrying this
     * reference in its agenda, skipping any already linked to an ERP meeting.
     *
     * @return array{id: mixed, join_url: string}|false|null The meeting; false when the list was read and nothing matches; null when it could not be read reliably.
     */
    private function findByReference(string $token, string $key)
    {
        $marker    = $this->marker($key);
        $pageToken = null;

        try {
            for ($page = 0; $page < 5; $page++) {
                $query = ['type' => 'scheduled', 'page_size' => 300] + ($pageToken ? ['next_page_token' => $pageToken] : []);
                $resp  = $this->http('get', self::MEETINGS_API, [
                    'query'   => $query,
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                ], 'zoom.list');
                $data  = json_decode((string) $resp->getBody(), true);

                if ($resp->getStatusCode() >= 300 || ! is_array($data) || ! isset($data['meetings']) || ! is_array($data['meetings'])) {
                    return null;
                }

                // Without the agenda on the entries there is nothing to match on, so an
                // empty answer cannot be trusted as "not created".
                foreach ($data['meetings'] as $entry) {
                    if (! array_key_exists('agenda', $entry)) {
                        return null;
                    }

                    if (str_contains((string) $entry['agenda'], $marker) && ! $this->linkedToErp((string) $entry['id'])) {
                        $joinUrl = $entry['join_url'] ?? $this->joinUrlOf($token, (string) $entry['id']);

                        return $joinUrl === null ? null : ['id' => $entry['id'], 'join_url' => $joinUrl];
                    }
                }

                $pageToken = $data['next_page_token'] ?? '';
                if ($pageToken === '') {
                    return false;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null; // more pages than are searched: cannot say it is absent
    }

    private function joinUrlOf(string $token, string $meetingId): ?string
    {
        $resp = $this->http('get', self::MEETING_API . rawurlencode($meetingId), ['headers' => ['Authorization' => 'Bearer ' . $token]], 'zoom.get');
        $data = json_decode((string) $resp->getBody(), true) ?? [];

        return $resp->getStatusCode() < 300 && ! empty($data['join_url']) ? (string) $data['join_url'] : null;
    }

    private function linkedToErp(string $zoomMeetingId): bool
    {
        return db_connect()->table('meetings')->where('provider', 'zoom')->where('provider_meeting_id', $zoomMeetingId)->countAllResults() > 0;
    }

    private function marker(string $key): string
    {
        return '[Ref ' . $key . ']';
    }

    public function updateMeeting(string $providerMeetingId, array $meeting, array $participantEmails): MeetingProviderResult
    {
        if (! $this->isConfigured()) {
            return MeetingProviderResult::fail('Zoom is not configured on this server.');
        }

        try {
            $token = $this->accessToken();
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Could not authenticate with Zoom: ' . $e->getMessage());
        }

        try {
            $response = $this->http('patch', self::MEETING_API . rawurlencode($providerMeetingId), [
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => json_encode($this->meetingBody($meeting)),
            ], 'zoom.update');
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Zoom API update failed: ' . $e->getMessage());
        }

        // Zoom's PATCH returns 204 No Content on success — there's no
        // fresh join_url to read back, so the existing one (already
        // stored on the ERP meeting row) is simply kept as-is.
        $status = $response->getStatusCode();
        if ($status >= 300) {
            $data    = json_decode((string) $response->getBody(), true) ?? [];
            $message = $data['message'] ?? ('HTTP ' . $status);

            return MeetingProviderResult::fail('Zoom API error: ' . $message);
        }

        return MeetingProviderResult::ok($providerMeetingId, null);
    }

    public function deleteMeeting(string $providerMeetingId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $token = $this->accessToken();
            $this->http('delete', self::MEETING_API . rawurlencode($providerMeetingId), [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ], 'zoom.delete');

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'ZoomProvider::deleteMeeting failed: ' . $e->getMessage());

            return false;
        }
    }

    private function meetingBody(array $meeting, ?string $key = null): array
    {
        $start    = $meeting['start_time'] ?: '09:00:00';
        $end      = $meeting['end_time'] ?: date('H:i:s', strtotime($start . ' +30 minutes'));
        $duration = max(1, (int) round((strtotime($end) - strtotime($start)) / 60));

        return [
            'topic'    => $meeting['title'],
            // Zoom caps the agenda at 2000 characters; the reference goes last and always fits.
            'agenda'   => $key === null
                ? ($meeting['agenda'] ?: '')
                : mb_substr((string) ($meeting['agenda'] ?: ''), 0, 1900) . "\n\n" . $this->marker($key),
            'type'     => 2, // scheduled meeting
            'start_time' => $meeting['meeting_date'] . 'T' . $start,
            'duration' => $duration,
            'timezone' => date_default_timezone_get(),
            'settings' => [
                'join_before_host' => false,
                'waiting_room'     => true,
            ],
        ];
    }

    private function resultFromMeetingResponse($response): MeetingProviderResult
    {
        $status = $response->getStatusCode();
        $data   = json_decode((string) $response->getBody(), true) ?? [];

        if ($status >= 300) {
            $message = $data['message'] ?? ('HTTP ' . $status);

            return MeetingProviderResult::fail('Zoom API error: ' . $message);
        }

        if (empty($data['join_url'])) {
            return MeetingProviderResult::fail('Zoom created the meeting but did not return a join URL.');
        }

        return MeetingProviderResult::ok((string) ($data['id'] ?? ''), $data['join_url']);
    }

    private function accessToken(): string
    {
        $response = $this->http('post', self::TOKEN_URL, [
            'query'   => ['grant_type' => 'account_credentials', 'account_id' => $this->config->zoomAccountId],
            'headers' => ['Authorization' => 'Basic ' . base64_encode($this->config->zoomClientId . ':' . $this->config->zoomClientSecret)],
        ], 'zoom.token');

        $data = json_decode((string) $response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 300 || empty($data['access_token'])) {
            throw new \RuntimeException($data['reason'] ?? $data['message'] ?? 'Token exchange failed.');
        }

        return $data['access_token'];
    }
}
