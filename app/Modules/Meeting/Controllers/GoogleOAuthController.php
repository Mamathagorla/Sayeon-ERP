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
    private const SCOPE     = GoogleOAuthConnectionModel::CALENDAR_SCOPE;

    protected GoogleOAuthConnectionModel $connectionModel;
    protected MeetingProviders $config;
    /** Replaced in tests so no request reaches Google. */
    public ?object $httpClient = null;

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
            $tokenClient = $this->httpClient ?? \Config\Services::curlrequest(['http_errors' => false]);
            $tokenResponse = $tokenClient->post(self::TOKEN_URL, [
                'connect_timeout' => 5,
                'timeout'         => 15,
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

            // Google's consent screen lets the user untick individual
            // permissions, so signing in successfully does not mean Calendar
            // access was granted. Nothing is stored in that case: a token
            // without the scope would only fail again at event creation.
            // The scope that counts is the one Google reports, never an
            // assumption that we got what we asked for.
            $grantedScope = (string) ($tokenData['scope'] ?? '');
            if (! GoogleOAuthConnectionModel::grantsCalendar($grantedScope)) {
                $this->connectionModel->markReconnectRequired((int) $this->currentUserId());

                return redirect()->to('/meetings/create')->with('error', 'Google did not grant calendar access. Click "Connect Google Account" again and tick the permission to view and edit events on your calendars (it is not ticked by default on some accounts), then press Continue.');
            }

            $googleEmail = $this->fetchGoogleEmail($tokenClient, $tokenData['access_token']);
            $existing    = $this->connectionModel->forUser((int) $this->currentUserId());

            // Google only returns a refresh token when it shows the consent
            // screen (connect() forces that). Without one there is nothing to
            // renew the access token with — unless this is the same Google
            // account already connected, whose stored refresh token is kept.
            $sameAccount = $existing !== null && ! empty($existing['refresh_token'])
                && $googleEmail !== null && strcasecmp((string) $existing['google_email'], $googleEmail) === 0;

            if (empty($tokenData['refresh_token']) && ! $sameAccount) {
                return redirect()->to('/meetings/create')->with('error', 'Google did not return a long-lived connection for this account. Remove this app under your Google account third-party access settings, then connect again.');
            }

            $data = [
                'google_email'     => $googleEmail,
                'access_token'     => $tokenData['access_token'],
                'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($tokenData['expires_in'] ?? 3600)),
                'scope'            => $grantedScope,
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
                'connect_timeout' => 5,
                'timeout'         => 10,
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
