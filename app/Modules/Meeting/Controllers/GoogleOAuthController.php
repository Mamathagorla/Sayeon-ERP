<?php

namespace App\Modules\Meeting\Controllers;

use App\Controllers\BaseController;
use App\Modules\Meeting\Models\GoogleOAuthConnectionModel;
use Config\MeetingProviders;

/**
 * Lets an individual ERP user connect their own Google account for real
 * Google Meet creation (see GoogleMeetProvider) — standard OAuth 2.0
 * "Web application" authorization-code flow, which works with any
 * Google account including a regular personal Gmail one. This is
 * deliberately per-user rather than a single shared/company-wide
 * credential: the alternative (a service account with domain-wide
 * delegation) only works on a paid Google Workspace account with admin
 * console access, which isn't available to every organizer.
 *
 * Every route here sits behind the normal 'auth' filter like the rest
 * of the app (not excluded in app/Config/Filters.php) — connecting a
 * Google account only makes sense for an already-logged-in ERP user,
 * unlike the Onboarding candidate portal's public token-based routes.
 */
class GoogleOAuthController extends BaseController
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE     = 'https://www.googleapis.com/auth/calendar.events';

    protected GoogleOAuthConnectionModel $connectionModel;
    protected MeetingProviders $config;

    public function __construct()
    {
        $this->connectionModel = new GoogleOAuthConnectionModel();
        $this->config           = config('MeetingProviders');
    }

    /**
     * Kicks off the consent flow — redirects the organizer to Google's
     * own OAuth screen. access_type=offline + prompt=consent guarantee
     * a refresh_token comes back even on a re-connect (Google only
     * issues one on the FIRST consent by default otherwise, which would
     * silently break reconnecting after a revoke).
     */
    public function connect()
    {
        if ($this->config->googleOAuthClientId === '' || $this->config->googleOAuthClientSecret === '') {
            return redirect()->to('/meetings/create')->with('error', 'Google Meet is not configured on this server yet — the app owner needs to add a Google OAuth client (see .env).');
        }

        // CSRF guard for the OAuth round-trip itself — checked back in
        // callback() against what Google hands back unchanged.
        $state = bin2hex(random_bytes(16));
        session()->set('google_oauth_state', $state);

        $params = [
            'client_id'     => $this->config->googleOAuthClientId,
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => self::SCOPE . ' https://www.googleapis.com/auth/userinfo.email',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ];

        return redirect()->to(self::AUTH_URL . '?' . http_build_query($params));
    }

    /**
     * Google redirects back here with either ?code=... (success) or
     * ?error=... (the user declined, or something else went wrong).
     * Exchanges the code for tokens and stores them against the
     * currently logged-in ERP user — never against whatever Google
     * account they happened to authorize with an assumption of identity
     * beyond that.
     */
    public function callback()
    {
        $error = $this->request->getGet('error');
        if ($error) {
            return redirect()->to('/meetings/create')->with('error', 'Google sign-in was cancelled or failed (' . $error . ').');
        }

        $state = $this->request->getGet('state');
        $expectedState = session()->get('google_oauth_state');
        session()->remove('google_oauth_state');

        if (! $state || ! $expectedState || ! hash_equals($expectedState, $state)) {
            return redirect()->to('/meetings/create')->with('error', 'Google sign-in could not be verified — please try connecting again.');
        }

        $code = $this->request->getGet('code');
        if (! $code) {
            return redirect()->to('/meetings/create')->with('error', 'Google did not return an authorization code.');
        }

        try {
            $tokenClient = \Config\Services::curlrequest(['http_errors' => false]);
            $tokenResponse = $tokenClient->post(self::TOKEN_URL, [
                'form_params' => [
                    'code'          => $code,
                    'client_id'     => $this->config->googleOAuthClientId,
                    'client_secret' => $this->config->googleOAuthClientSecret,
                    'redirect_uri'  => $this->redirectUri(),
                    'grant_type'    => 'authorization_code',
                ],
            ]);

            $tokenData = json_decode((string) $tokenResponse->getBody(), true) ?? [];

            if ($tokenResponse->getStatusCode() >= 300 || empty($tokenData['access_token'])) {
                throw new \RuntimeException($tokenData['error_description'] ?? 'Token exchange failed.');
            }

            // access_type=offline only returns refresh_token on the
            // FIRST-ever consent for a given client+account pairing
            // unless prompt=consent forces it every time (see connect())
            // — still guarded here in case Google ever omits it, so a
            // reconnect doesn't silently wipe a working refresh_token
            // with null.
            $googleEmail = $this->fetchGoogleEmail($tokenClient, $tokenData['access_token']);

            $data = [
                'google_email'     => $googleEmail,
                'access_token'     => $tokenData['access_token'],
                'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($tokenData['expires_in'] ?? 3600)),
                'scope'            => $tokenData['scope'] ?? self::SCOPE,
            ];

            if (! empty($tokenData['refresh_token'])) {
                $data['refresh_token'] = $tokenData['refresh_token'];
            }

            $this->connectionModel->saveForUser((int) $this->currentUserId(), $data);
            $this->logActivity('meeting', 'update', null, 'Connected Google account (' . ($googleEmail ?? 'unknown') . ') for Google Meet');
        } catch (\Throwable $e) {
            return redirect()->to('/meetings/create')->with('error', 'Could not complete Google sign-in: ' . $e->getMessage());
        }

        return redirect()->to('/meetings/create')->with('success', 'Google account connected — you can now create real Google Meet meetings.');
    }

    public function disconnect()
    {
        $this->connectionModel->disconnectUser((int) $this->currentUserId());
        $this->logActivity('meeting', 'update', null, 'Disconnected Google account');

        return redirect()->back()->with('success', 'Google account disconnected.');
    }

    private function fetchGoogleEmail($client, string $accessToken): ?string
    {
        try {
            $response = $client->get('https://www.googleapis.com/oauth2/v2/userinfo', [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            ]);
            $data = json_decode((string) $response->getBody(), true) ?? [];

            return $data['email'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function redirectUri(): string
    {
        return $this->config->googleOAuthRedirectUri !== ''
            ? $this->config->googleOAuthRedirectUri
            : site_url('meetings/google/callback');
    }
}
