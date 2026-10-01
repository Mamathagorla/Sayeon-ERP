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
    private const TOKEN_URL   = 'https://zoom.us/oauth/token';
    private const MEETINGS_API = 'https://api.zoom.us/v2/users/me/meetings';
    private const MEETING_API  = 'https://api.zoom.us/v2/meetings/';

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

        try {
            $response = $this->client()->post(self::MEETINGS_API, [
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => json_encode($this->meetingBody($meeting)),
            ]);
        } catch (\Throwable $e) {
            return MeetingProviderResult::fail('Zoom API request failed: ' . $e->getMessage());
        }

        return $this->resultFromMeetingResponse($response);
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
            $response = $this->client()->patch(self::MEETING_API . rawurlencode($providerMeetingId), [
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                'body'    => json_encode($this->meetingBody($meeting)),
            ]);
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
            $this->client()->delete(self::MEETING_API . rawurlencode($providerMeetingId), [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'ZoomProvider::deleteMeeting failed: ' . $e->getMessage());

            return false;
        }
    }

    private function meetingBody(array $meeting): array
    {
        $start    = $meeting['start_time'] ?: '09:00:00';
        $end      = $meeting['end_time'] ?: date('H:i:s', strtotime($start . ' +30 minutes'));
        $duration = max(1, (int) round((strtotime($end) - strtotime($start)) / 60));

        return [
            'topic'    => $meeting['title'],
            'agenda'   => $meeting['agenda'] ?: '',
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
        $response = $this->client()->post(self::TOKEN_URL, [
            'query'   => ['grant_type' => 'account_credentials', 'account_id' => $this->config->zoomAccountId],
            'headers' => ['Authorization' => 'Basic ' . base64_encode($this->config->zoomClientId . ':' . $this->config->zoomClientSecret)],
        ]);

        $data = json_decode((string) $response->getBody(), true) ?? [];

        if ($response->getStatusCode() >= 300 || empty($data['access_token'])) {
            throw new \RuntimeException($data['reason'] ?? $data['message'] ?? 'Token exchange failed.');
        }

        return $data['access_token'];
    }

    private function client()
    {
        return \Config\Services::curlrequest(['http_errors' => false]);
    }
}
