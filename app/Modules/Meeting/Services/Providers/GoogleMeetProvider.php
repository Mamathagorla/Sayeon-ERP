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
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

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

        $connection = $this->connectionModel->forUser((int) session('userId'));

        return $connection !== null && ! empty($connection['refresh_token']);
    }

    public function createMeeting(array $meeting, array $participantEmails): MeetingProviderResult
    {
        if (! $this->isConfigured()) {
            return MeetingProviderResult::fail('Google Meet is not connected for your account yet — connect your Google account from the meeting form first.');
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
            $response = $this->client()->post(self::CALENDAR_API, [
                'query'   => ['conferenceDataVersion' => 1, 'sendUpdates' => 'all'],
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => json_encode($body),
            ]);
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Google Calendar API request failed: ' . $e->getMessage());
        }

        return $this->resultFromEventResponse($response);
    }

    public function updateMeeting(string $providerMeetingId, array $meeting, array $participantEmails): MeetingProviderResult
    {
        if (! $this->isConfigured()) {
            return MeetingProviderResult::fail('Google Meet is not connected for your account.');
        }

        try {
            $token = $this->accessToken();
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Could not authenticate with your Google account: ' . $e->getMessage());
        }

        $body = $this->eventBody($meeting, $participantEmails);

        try {
            $response = $this->client()->patch(self::CALENDAR_API . '/' . rawurlencode($providerMeetingId), [
                'query'   => ['conferenceDataVersion' => 1, 'sendUpdates' => 'all'],
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => json_encode($body),
            ]);
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
            $token = $this->accessToken();
            $this->client()->delete(self::CALENDAR_API . '/' . rawurlencode($providerMeetingId), [
                'query'   => ['sendUpdates' => 'all'],
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

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
        $timezone = date_default_timezone_get();
        $date     = $meeting['meeting_date'];
        $start    = $meeting['start_time'] ?: '09:00:00';
        $end      = $meeting['end_time'] ?: date('H:i:s', strtotime($start . ' +30 minutes'));

        return [
            'summary'     => $meeting['title'],
            'description' => $meeting['agenda'] ?: '',
            'start'       => ['dateTime' => "{$date}T{$start}", 'timeZone' => $timezone],
            'end'         => ['dateTime' => "{$date}T{$end}", 'timeZone' => $timezone],
            'attendees'   => array_map(static fn (string $email) => ['email' => $email], $participantEmails),
        ];
    }

    private function resultFromEventResponse($response): MeetingProviderResult
    {
        $status = $response->getStatusCode();
        $data   = json_decode((string) $response->getBody(), true) ?? [];

        if ($status >= 300) {
            $message = $data['error']['message'] ?? ('HTTP ' . $status);

            return MeetingProviderResult::fail('Google Calendar API error: ' . $message);
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

        $expiresAt = $connection['token_expires_at'] ?? null;
        $stillValid = $connection['access_token'] && $expiresAt && strtotime($expiresAt) > time() + 60;

        if ($stillValid) {
            return $connection['access_token'];
        }

        $response = $this->client()->post(self::TOKEN_URL, [
            'form_params' => [
                'client_id'     => $this->config->googleOAuthClientId,
                'client_secret' => $this->config->googleOAuthClientSecret,
                'refresh_token' => $connection['refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 300 || empty($data['access_token'])) {
            throw new \RuntimeException($data['error_description'] ?? 'Could not refresh the Google access token — the connection may have been revoked; try reconnecting.');
        }

        $this->connectionModel->saveForUser($userId, [
            'access_token'     => $data['access_token'],
            'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($data['expires_in'] ?? 3600)),
        ]);

        return $data['access_token'];
    }

    private function client()
    {
        return \Config\Services::curlrequest(['http_errors' => false]);
    }
}
