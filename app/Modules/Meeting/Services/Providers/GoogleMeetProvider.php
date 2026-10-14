<?php

namespace App\Modules\Meeting\Services\Providers;

use App\Modules\Meeting\Models\GoogleOAuthConnectionModel;
use Config\MeetingProviders;

/**
 * Creates a real Google Meet link via the official Google Calendar API's
 * conferenceData mechanism (Calendar API, not a separate "Meet API" —
 * this is the documented, official way Google Meet links are created
 * programmatically: a Calendar event with conferenceData.createRequest).
 *
 * Auth: a per-organizer OAuth 2.0 connection (see GoogleOAuthController /
 * google_oauth_connections), NOT a service account with domain-wide
 * delegation — delegation only works on a paid Google Workspace account
 * with admin console access, which isn't available to an organizer using
 * a regular personal Gmail account. Each organizer connects their own
 * Google account once (one-time consent screen); this class then acts
 * on THEIR OWN calendar using their stored refresh token, refreshing the
 * short-lived access token on demand.
 *
 * Resolves "which organizer" from the current session (session('userId'))
 * rather than a constructor/method parameter — keeps
 * MeetingProviderInterface identical for every provider (Zoom's
 * Server-to-Server grant is company-wide, not per-user, so it has no
 * equivalent need), and every call site already runs inside an
 * authenticated request.
 */
class GoogleMeetProvider implements MeetingProviderInterface
{
    use BoundedHttp;

    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    // 'primary' is the signed-in organizer's own calendar: the account whose
    // tokens are stored for the current ERP user, never a shared one.
    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    private const RECONNECT_HINT = 'Reconnect your Google account from the meeting form and tick the calendar permission when Google asks.';

    private MeetingProviders $config;
    private GoogleOAuthConnectionModel $connectionModel;

    public function __construct()
    {
        $this->config          = config('MeetingProviders');
        $this->connectionModel = new GoogleOAuthConnectionModel();
    }

    public function isConfigured(): bool
    {
        if ($this->config->googleOAuthClientId === '' || $this->config->googleOAuthClientSecret === '') {
            return false;
        }

        return GoogleOAuthConnectionModel::isUsable($this->connectionModel->forUser((int) session('userId')));
    }

    public function createMeeting(array $meeting, array $participantEmails): MeetingProviderResult
    {
        if (! $this->isConfigured()) {
            return MeetingProviderResult::fail($this->notConnectedMessage());
        }

        if ($error = $this->invalidTimeRange($meeting)) {
            return MeetingProviderResult::fail($error);
        }

        try {
            $token = $this->accessToken();
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Could not authenticate with your Google account: ' . $e->getMessage() . ' — try reconnecting your Google account.');
        }

        $body = $this->eventBody($meeting, $participantEmails);
        $body['conferenceData'] = [
            'createRequest' => [
                'requestId'             => bin2hex(random_bytes(12)),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ],
        ];

        try {
            $response = $this->http('post', self::CALENDAR_API, [
                'query'   => ['conferenceDataVersion' => 1, 'sendUpdates' => 'all'],
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => json_encode($body),
            ], 'google.create');
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Google Calendar API request failed: ' . $e->getMessage());
        }

        return $this->resultFromEventResponse($response);
    }

    public function updateMeeting(string $providerMeetingId, array $meeting, array $participantEmails): MeetingProviderResult
    {
        if (! $this->isConfigured()) {
            return MeetingProviderResult::fail($this->notConnectedMessage());
        }

        if ($error = $this->invalidTimeRange($meeting)) {
            return MeetingProviderResult::fail($error);
        }

        try {
            $token = $this->accessToken();
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Could not authenticate with your Google account: ' . $e->getMessage());
        }

        $body = $this->eventBody($meeting, $participantEmails);

        try {
            $response = $this->http('patch', self::CALENDAR_API . '/' . rawurlencode($providerMeetingId), [
                'query'   => ['conferenceDataVersion' => 1, 'sendUpdates' => 'all'],
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => json_encode($body),
            ], 'google.update');
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Google Calendar API update failed: ' . $e->getMessage());
        }

        return $this->resultFromEventResponse($response);
    }

    public function deleteMeeting(string $providerMeetingId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $token    = $this->accessToken();
            $response = $this->http('delete', self::CALENDAR_API . '/' . rawurlencode($providerMeetingId), [
                'query'   => ['sendUpdates' => 'all'],
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ], 'google.delete');

            // 404/410 means the event is already gone on Google's side, which
            // is the outcome cancellation was meant to achieve.
            $status = $response->getStatusCode();
            if ($status >= 300 && ! in_array($status, [404, 410], true)) {
                log_message('error', 'GoogleMeetProvider::deleteMeeting HTTP ' . $status . ': ' . (string) $response->getBody());

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'GoogleMeetProvider::deleteMeeting failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @return array{summary: string, description: ?string, start: array, end: array, attendees: array}
     */
    private function eventBody(array $meeting, array $participantEmails): array
    {
        $timezone = $meeting['timezone'] ?? config('App')->appTimezone;
        $date     = $meeting['meeting_date'];
        $endDate  = $meeting['end_date'] ?? $date;
        [$startTime, $endTime] = $this->times($meeting);

        return [
            'summary'     => $meeting['title'],
            'description' => $meeting['agenda'] ?: '',
            'start'       => ['dateTime' => "{$date}T{$startTime}", 'timeZone' => $timezone],
            'end'         => ['dateTime' => "{$endDate}T{$endTime}", 'timeZone' => $timezone],
            'attendees'   => array_map(static fn (string $email) => ['email' => $email], $participantEmails),
        ];
    }

    /**
     * Google requires RFC 3339 date-times, which include seconds. The
     * form's time inputs post "HH:MM" (no seconds), so every value is
     * normalized to "HH:MM:SS" here. Also returns the start/end pair
     * used for the start < end check below.
     *
     * @return array{0: string, 1: string}
     */
    private function times(array $meeting): array
    {
        $start = date('H:i:s', strtotime($meeting['start_time'] ?: '09:00:00'));
        $end   = $meeting['end_time']
            ? date('H:i:s', strtotime($meeting['end_time']))
            : date('H:i:s', strtotime($start . ' +30 minutes'));

        return [$start, $end];
    }

    private function invalidTimeRange(array $meeting): ?string
    {
        [$start, $end] = $this->times($meeting);
        $startAt = strtotime($meeting['meeting_date'] . ' ' . $start);
        $endAt   = strtotime(($meeting['end_date'] ?? $meeting['meeting_date']) . ' ' . $end);

        return $endAt > $startAt ? null : 'End time must be after start time for the meeting.';
    }

    private function resultFromEventResponse($response): MeetingProviderResult
    {
        $status = $response->getStatusCode();
        $raw    = (string) $response->getBody();
        $data   = json_decode($raw, true) ?? [];

        if ($status >= 300) {
            // Google's top-level message is often just "Bad Request"; the
            // specific reason is in error.errors[]. Both are surfaced, and
            // the full body is logged for diagnosis (it holds no tokens).
            $detail = $data['error']['errors'][0]['message'] ?? $data['error']['message'] ?? ('HTTP ' . $status);
            log_message('error', 'Google Calendar events API returned HTTP ' . $status . ': ' . $raw);

            return MeetingProviderResult::fail($this->guidanceFor($status, $data) ?? 'Google Calendar API error: ' . $detail);
        }

        $joinUrl = $data['hangoutLink'] ?? null;
        if ($joinUrl === null) {
            foreach ($data['conferenceData']['entryPoints'] ?? [] as $entry) {
                if (($entry['entryPointType'] ?? null) === 'video') {
                    $joinUrl = $entry['uri'] ?? null;
                    break;
                }
            }
        }

        if ($joinUrl === null) {
            return MeetingProviderResult::fail('Google created the event but did not return a Meet link.');
        }

        return MeetingProviderResult::ok($data['id'] ?? null, $joinUrl);
    }

    /**
     * A valid access token for the CURRENT session user's connected
     * Google account — refreshed via the stored refresh_token whenever
     * the cached access_token is missing or within a minute of expiry,
     * and the refreshed value is written straight back to the
     * connection row so the next call reuses it instead of refreshing
     * on every single request.
     */
    private function accessToken(): string
    {
        $userId     = (int) session('userId');
        $connection = $this->connectionModel->forUser($userId);

        if ($connection === null || empty($connection['refresh_token'])) {
            throw new \RuntimeException('No connected Google account found.');
        }

        if (! GoogleOAuthConnectionModel::grantsCalendar($connection['scope'] ?? null)) {
            throw new \RuntimeException($this->notConnectedMessage());
        }

        $expiresAt = $connection['token_expires_at'] ?? null;
        $stillValid = $connection['access_token'] && $expiresAt && strtotime($expiresAt) > time() + 60;

        if ($stillValid) {
            return $connection['access_token'];
        }

        $response = $this->http('post', self::TOKEN_URL, [
            'form_params' => [
                'client_id'     => $this->config->googleOAuthClientId,
                'client_secret' => $this->config->googleOAuthClientSecret,
                'refresh_token' => $connection['refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ], 'google.token');

        $data = json_decode((string) $response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 300 || empty($data['access_token'])) {
            // invalid_grant: the user revoked access, the refresh token expired
            // or the password changed. Retrying can never succeed, so the
            // connection is flagged and the organizer is asked to reconnect.
            if (($data['error'] ?? null) === 'invalid_grant') {
                $this->connectionModel->markReconnectRequired($userId);

                throw new \RuntimeException('Google access was revoked or has expired. ' . self::RECONNECT_HINT);
            }

            throw new \RuntimeException($data['error_description'] ?? 'Could not refresh the Google access token. ' . self::RECONNECT_HINT);
        }

        $update = [
            'access_token'     => $data['access_token'],
            'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($data['expires_in'] ?? 3600)),
        ];

        // A refresh normally returns no refresh_token (the stored one is kept;
        // saveForUser() never lets an absent one erase it). A scope list in the
        // response is the truth about what this grant allows now.
        if (! empty($data['refresh_token'])) {
            $update['refresh_token'] = $data['refresh_token'];
        }
        if (isset($data['scope'])) {
            $update['scope'] = $data['scope'];

            if (! GoogleOAuthConnectionModel::grantsCalendar($data['scope'])) {
                $this->connectionModel->markReconnectRequired($userId);

                throw new \RuntimeException($this->notConnectedMessage());
            }
        }

        $this->connectionModel->saveForUser($userId, $update);

        return $data['access_token'];
    }

    private function notConnectedMessage(): string
    {
        $connection = $this->connectionModel->forUser((int) session('userId'));

        if ($connection !== null && ! empty($connection['refresh_token']) && ! GoogleOAuthConnectionModel::grantsCalendar($connection['scope'] ?? null)) {
            return 'Your Google connection does not include permission to create calendar events. ' . self::RECONNECT_HINT;
        }

        return 'Google Meet is not connected for your account yet. Connect your Google account from the meeting form first.';
    }

    /**
     * Plain-language guidance for the Google errors an organizer can fix, or
     * null for anything else (the raw Google message is shown then). Errors
     * that mean the stored grant is no good also flag the connection so the
     * next attempt starts a fresh consent instead of replaying the same token.
     */
    private function guidanceFor(int $status, array $data): ?string
    {
        $reasons = [];
        foreach ($data['error']['errors'] ?? [] as $e) {
            $reasons[] = (string) ($e['reason'] ?? '');
        }
        foreach ($data['error']['details'] ?? [] as $d) {
            $reasons[] = (string) ($d['reason'] ?? '');
        }
        $reasons[] = (string) ($data['error']['status'] ?? '');

        $has = static fn (string ...$wanted): bool => array_intersect($wanted, $reasons) !== [];

        if ($has('insufficientPermissions', 'ACCESS_TOKEN_SCOPE_INSUFFICIENT') || ($status === 403 && stripos((string) ($data['error']['message'] ?? ''), 'insufficient') !== false)) {
            $this->connectionModel->markReconnectRequired((int) session('userId'));

            return 'Google rejected the request because calendar permission was not granted. ' . self::RECONNECT_HINT;
        }

        if ($status === 401 || $has('authError', 'UNAUTHENTICATED', 'invalid_grant')) {
            $this->connectionModel->markReconnectRequired((int) session('userId'));

            return 'Google no longer accepts your connection (it may have been revoked). ' . self::RECONNECT_HINT;
        }

        if ($has('accessNotConfigured', 'SERVICE_DISABLED')) {
            return 'The Google Calendar API is not enabled for this application\'s Google Cloud project. Ask the system owner to enable it in Google Cloud Console (APIs and Services, Library, Google Calendar API).';
        }

        if ($status === 404 || $has('notFound')) {
            return 'Google could not find or open the calendar or event. Check that the connected Google account owns the calendar, or ' . lcfirst(self::RECONNECT_HINT);
        }

        if ($status === 403 && $has('forbidden', 'PERMISSION_DENIED')) {
            return 'The connected Google account is not allowed to create events on this calendar. Use an account with edit access, or ' . lcfirst(self::RECONNECT_HINT);
        }

        return null;
    }
}
