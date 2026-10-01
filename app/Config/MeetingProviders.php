<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Credentials/config for the real Google Meet / Zoom integration (see
 * app/Modules/Meeting/Services/Providers/) — every value comes from
 * .env, never hardcoded, per this app's existing convention (compare
 * app/Config/Email.php, app/Config/CURLRequest.php). None of these are
 * set out of the box; until they are, GoogleMeetProvider/ZoomProvider
 * report isConfigured() === false and MeetingController refuses to
 * create a fake link instead (see its store()/update() methods).
 *
 * Google Meet uses the Google Calendar API's conferenceData mechanism
 * (the official, documented way to create a Meet link via API — there
 * is no separate standalone "Meet API" for arbitrary meeting creation),
 * authenticated via a per-organizer OAuth 2.0 connection (see
 * GoogleOAuthController / google_oauth_connections) rather than a
 * service account with domain-wide delegation — domain-wide delegation
 * only exists on a paid Google Workspace account with admin console
 * access, so it can't work for an organizer using a regular personal
 * Gmail account. A standard OAuth "Web application" client, by
 * contrast, works with any Google account: each organizer connects
 * their own account once (one-time consent screen) and the app stores
 * their refresh token to act on their own calendar from then on.
 *
 * Zoom uses a Server-to-Server OAuth app (the current recommended Zoom
 * integration method — the older JWT app type is deprecated/retired).
 * Zoom's flow is company-wide (one app credential set for the whole
 * ERP), unlike Google's per-organizer connection, since Zoom's
 * Server-to-Server grant has no equivalent to Workspace's delegation
 * restriction.
 */
class MeetingProviders extends BaseConfig
{
    // Google Cloud OAuth 2.0 "Web application" client (Console → APIs &
    // Services → Credentials → Create Credentials → OAuth client ID).
    // The Calendar API must be enabled on the same project.
    public string $googleOAuthClientId;
    public string $googleOAuthClientSecret;

    // Must exactly match a "Authorized redirect URI" registered on that
    // OAuth client — normally site_url('meetings/google/callback'), but
    // kept explicit/configurable since it must be reachable from
    // wherever this app is actually deployed.
    public string $googleOAuthRedirectUri;

    // Zoom Server-to-Server OAuth app credentials (Zoom App Marketplace
    // → Develop → Build App → Server-to-Server OAuth).
    public string $zoomAccountId;
    public string $zoomClientId;
    public string $zoomClientSecret;

    public function __construct()
    {
        parent::__construct();

        $this->googleOAuthClientId     = env('meetingProviders.googleOAuthClientId', '');
        $this->googleOAuthClientSecret = env('meetingProviders.googleOAuthClientSecret', '');
        // Left as the raw env value (possibly '') rather than defaulted
        // here — Config classes can be constructed before the url
        // helper/base URL are safely available (e.g. very early in CLI
        // bootstrapping), so the site_url('meetings/google/callback')
        // fallback is computed where it's actually used instead (see
        // GoogleOAuthController::redirectUri()).
        $this->googleOAuthRedirectUri  = env('meetingProviders.googleOAuthRedirectUri', '');
        $this->zoomAccountId           = env('meetingProviders.zoomAccountId', '');
        $this->zoomClientId            = env('meetingProviders.zoomClientId', '');
        $this->zoomClientSecret        = env('meetingProviders.zoomClientSecret', '');
    }
}
