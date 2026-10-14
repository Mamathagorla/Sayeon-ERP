<?php

namespace Tests\E2E;

use App\Modules\Meeting\Models\GoogleOAuthConnectionModel;
use App\Modules\Meeting\Services\Providers\GoogleMeetProvider;
use App\Modules\Meeting\Services\Providers\ZoomProvider;
use Config\Services;
use Tests\Support\E2ETestCase;

/**
 * Google Meet / Zoom meeting creation with the outside world replaced by a
 * fake HTTP client: nothing here reaches Google or Zoom, and no real token or
 * secret is used.
 */
final class MeetingProvidersTest extends E2ETestCase
{
    private const CAL = GoogleOAuthConnectionModel::CALENDAR_SCOPE;

    private array $w;
    private object $http;

    protected function setUp(): void
    {
        parent::setUp();
        $this->w = $this->world();

        $cfg = config('MeetingProviders');
        $cfg->googleOAuthClientId     = 'test-client';
        $cfg->googleOAuthClientSecret = 'test-secret';
        $cfg->zoomAccountId           = 'acct';
        $cfg->zoomClientId            = 'zid';
        $cfg->zoomClientSecret        = 'zsecret';

        $this->http = new class () {
            /** @var array<int, array{method: string, url: string, options: array}> */
            public array $calls = [];
            /** @var array<string, array<int, array{0: int, 1: array}>> url fragment => queued responses */
            public array $queue = [];

            public function __call(string $method, array $args)
            {
                [$url, $options] = $args + [1 => []];
                $this->calls[] = ['method' => $method, 'url' => $url, 'options' => $options];

                foreach ($this->queue as $fragment => &$responses) {
                    // Keys may start with the HTTP method ("post https://...") to tell a create from a list.
                    if (str_contains($method . ' ' . $url, $fragment) && $responses !== []) {
                        [$status, $body] = count($responses) > 1 ? array_shift($responses) : $responses[0];

                        if ($body instanceof \Throwable) {
                            throw $body;
                        }

                        return new class ($status, $body) {
                            public function __construct(private int $s, private array $b)
                            {
                            }

                            public function getStatusCode(): int
                            {
                                return $this->s;
                            }

                            public function getBody(): string
                            {
                                return json_encode($this->b);
                            }
                        };
                    }
                }

                throw new \RuntimeException('Unexpected HTTP call in test: ' . $method . ' ' . parse_url($url, PHP_URL_HOST));
            }

            public function callsTo(string $fragment): array
            {
                return array_values(array_filter($this->calls, static fn ($c) => str_contains($c['url'], $fragment)));
            }
        };
        Services::injectMock('curlrequest', $this->http);
    }

    protected function tearDown(): void
    {
        Services::reset(true);
        parent::tearDown();
    }

    private function connect(array $person, array $over = []): int
    {
        $userId = (int) $person['user_id'];
        (new GoogleOAuthConnectionModel())->saveForUser($userId, $over + [
            'google_email'     => 'organizer@example.test',
            'access_token'     => 'access-1',
            'refresh_token'    => 'refresh-1',
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'scope'            => self::CAL . ' https://www.googleapis.com/auth/userinfo.email',
        ]);

        return $userId;
    }

    private function meeting(): array
    {
        return ['title' => 'Review', 'agenda' => 'x', 'meeting_date' => date('Y-m-d', strtotime('+2 days')), 'start_time' => '10:00', 'end_time' => '11:00', 'timezone' => 'Asia/Kolkata'];
    }

    private function actingAsProvider(int $userId): GoogleMeetProvider
    {
        session()->set('userId', $userId);

        return new GoogleMeetProvider();
    }

    private function event(string $id = 'evt1'): array
    {
        return [200, ['id' => $id, 'hangoutLink' => 'https://meet.google.com/abc-defg-hij']];
    }

    public function testMissingCalendarScopeIsNotConnectedAndNeverCallsGoogle(): void
    {
        $uid = $this->connect($this->w['A']['manager'], ['scope' => 'https://www.googleapis.com/auth/userinfo.email']);
        $res = $this->actingAsProvider($uid)->createMeeting($this->meeting(), []);

        $this->assertFalse($res->success);
        $this->assertStringContainsString('permission', $res->errorMessage);
        $this->assertStringContainsString('Reconnect', $res->errorMessage);
        $this->assertSame([], $this->http->calls, 'no Google call is made with a token that cannot work');
    }

    public function testCreateUsesPrimaryCalendarAndConferenceDataVersion(): void
    {
        $uid = $this->connect($this->w['A']['manager']);
        $this->http->queue['calendars/primary/events'] = [$this->event()];

        $res = $this->actingAsProvider($uid)->createMeeting($this->meeting(), ['a@example.test']);

        $this->assertTrue($res->success);
        $this->assertSame('evt1', $res->providerMeetingId);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $res->joinUrl);

        $call = $this->http->callsTo('calendars/primary/events')[0];
        $this->assertSame(1, $call['options']['query']['conferenceDataVersion']);
        $this->assertSame('Bearer access-1', $call['options']['headers']['Authorization']);
        $this->assertArrayHasKey('conferenceData', json_decode($call['options']['body'], true));
        $this->assertGreaterThan(0, $call['options']['timeout'], 'every call is time-bounded');
        $this->assertGreaterThan(0, $call['options']['connect_timeout']);
    }

    public function testInsufficientPermissionGivesReconnectGuidanceAndForcesFreshConsent(): void
    {
        $uid = $this->connect($this->w['A']['manager']);
        $this->http->queue['calendars/primary/events'] = [[403, ['error' => ['code' => 403, 'message' => 'Request had insufficient authentication scopes.', 'errors' => [['reason' => 'insufficientPermissions', 'message' => 'Insufficient Permission']]]]]];

        $provider = $this->actingAsProvider($uid);
        $res      = $provider->createMeeting($this->meeting(), []);

        $this->assertFalse($res->success);
        $this->assertStringContainsString('calendar permission was not granted', $res->errorMessage);
        $this->assertStringContainsString('Reconnect', $res->errorMessage);
        $this->assertFalse($provider->isConfigured(), 'the stale grant is not reused; the organizer must consent again');
        $this->assertCount(1, $this->http->callsTo('calendars/primary/events'), 'no automatic retry that could duplicate an event');
    }

    public function testRevokedRefreshTokenAsksToReconnect(): void
    {
        $uid = $this->connect($this->w['A']['manager'], ['token_expires_at' => date('Y-m-d H:i:s', time() - 10)]);
        $this->http->queue['oauth2.googleapis.com/token'] = [[400, ['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.']]];

        $provider = $this->actingAsProvider($uid);
        $res      = $provider->createMeeting($this->meeting(), []);

        $this->assertFalse($res->success);
        $this->assertStringContainsString('revoked', $res->errorMessage);
        $this->assertFalse($provider->isConfigured());
        $this->assertSame([], $this->http->callsTo('calendars'), 'no event call with a dead token');
    }

    public function testRefreshKeepsRefreshTokenAndStoresTokensEncrypted(): void
    {
        $uid = $this->connect($this->w['A']['manager'], ['token_expires_at' => date('Y-m-d H:i:s', time() - 10)]);
        $this->http->queue['oauth2.googleapis.com/token']  = [[200, ['access_token' => 'access-2', 'expires_in' => 3600]]];
        $this->http->queue['calendars/primary/events']     = [$this->event()];

        $res = $this->actingAsProvider($uid)->createMeeting($this->meeting(), []);

        $this->assertTrue($res->success);
        $this->assertSame('Bearer access-2', $this->http->callsTo('calendars')[0]['options']['headers']['Authorization']);

        $model = new GoogleOAuthConnectionModel();
        $row   = $model->forUser($uid);
        $this->assertSame('access-2', $row['access_token']);
        $this->assertSame('refresh-1', $row['refresh_token'], 'a refresh that returns no refresh_token must not erase the stored one');

        $raw = db_connect()->table('google_oauth_connections')->where('user_id', $uid)->get()->getRowArray();
        $this->assertStringNotContainsString('refresh-1', $raw['refresh_token']);
        $this->assertStringNotContainsString('access-2', $raw['access_token']);

        $model->saveForUser($uid, ['refresh_token' => '', 'access_token' => 'access-3']);
        $this->assertSame('refresh-1', $model->forUser($uid)['refresh_token'], 'an empty refresh_token never overwrites a valid one');
    }

    public function testCallbackWithoutCalendarScopeStoresNothingThenReconnectSucceeds(): void
    {
        $manager = $this->w['A']['manager'];
        $this->actAs($manager);
        $this->as['google_oauth_state'] = 'state-1';

        // The user unticked the calendar permission on Google's consent screen.
        $this->http->queue['oauth2.googleapis.com/token'] = [[200, ['access_token' => 'a1', 'refresh_token' => 'r1', 'expires_in' => 3600, 'scope' => 'https://www.googleapis.com/auth/userinfo.email openid']]];
        $this->visit('/meetings/google/callback?state=state-1&code=abc');

        $this->assertFlash('error', 'did not grant calendar access');
        $this->assertNull((new GoogleOAuthConnectionModel())->forUser((int) $manager['user_id']), 'no unusable token is saved');

        // Reconnecting with the permission ticked.
        $this->as['google_oauth_state'] = 'state-2';
        $this->http->queue = [
            'oauth2.googleapis.com/token' => [[200, ['access_token' => 'a2', 'refresh_token' => 'r2', 'expires_in' => 3600, 'scope' => self::CAL . ' https://www.googleapis.com/auth/userinfo.email']]],
            'oauth2/v2/userinfo'          => [[200, ['email' => 'organizer@example.test']]],
        ];
        $this->visit('/meetings/google/callback?state=state-2&code=def');

        $this->assertFlash('success', 'Google account connected');
        $row = (new GoogleOAuthConnectionModel())->forUser((int) $manager['user_id']);
        $this->assertSame('r2', $row['refresh_token']);
        $this->assertTrue(GoogleOAuthConnectionModel::isUsable($row));
    }

    public function testRetriedSubmissionDoesNotCreateASecondGoogleEvent(): void
    {
        $manager = $this->w['A']['manager'];
        $this->connect($manager);
        $this->http->queue['calendars/primary/events'] = [$this->event()];

        $form = [
            'title' => 'Retry me', 'company_id' => $this->w['A']['company']['id'], 'meeting_date' => date('Y-m-d', strtotime('+2 days')),
            'start_time' => '10:00', 'end_time' => '11:00', 'provider' => 'google_meet',
            'participant_user_ids' => [$this->w['A']['employee']['user_id']],
        ];

        $this->actAs($manager)->submit('/meetings', $form);
        $this->actAs($manager)->submit('/meetings', $form);

        $this->assertSame(1, $this->countRows('meetings', ['title' => 'Retry me']));
        $this->assertCount(1, $this->http->callsTo('calendars/primary/events'), 'the retry reuses the first meeting');
        $this->assertFlash('success', 'already scheduled');
    }

    public function testZoomCallsAreBoundedAndEmailTrouble_DoesNotFailCreation(): void
    {
        $manager = $this->w['A']['manager'];
        $this->http->queue['zoom.us/oauth/token']      = [[200, ['access_token' => 'zt']]];
        $this->http->queue['api.zoom.us/v2/users/me/meetings'] = [[201, ['id' => 555, 'join_url' => 'https://zoom.us/j/555']]];

        $this->actAs($manager)->submit('/meetings', [
            'title' => 'Zoom sync', 'company_id' => $this->w['A']['company']['id'], 'meeting_date' => date('Y-m-d', strtotime('+2 days')),
            'start_time' => '10:00', 'end_time' => '11:00', 'provider' => 'zoom',
            'participant_user_ids' => [$this->w['A']['employee']['user_id']],
        ]);

        // SMTP is blanked in the harness (NOT_CONFIGURED), which is the same "email did not go out" path as a failed send.
        $m = $this->row('meetings', ['title' => 'Zoom sync']);
        $this->assertNotNull($m, 'meeting saved: ' . json_encode($this->flash('error')));
        $this->assertSame('555', $m['provider_meeting_id']);
        $this->assertSame('https://zoom.us/j/555', $m['meeting_link']);
        $this->assertNull($this->flash('error'));
        $this->assertFlash('success', 'Meeting scheduled');

        foreach ($this->http->calls as $call) {
            $this->assertGreaterThan(0, $call['options']['timeout'], $call['url'] . ' has no overall timeout');
            $this->assertLessThanOrEqual(30, $call['options']['timeout']);
            $this->assertGreaterThan(0, $call['options']['connect_timeout']);
        }
        $this->assertCount(1, $this->http->callsTo('users/me/meetings'));

        $zoom = new ZoomProvider();
        $this->assertTrue($zoom->isConfigured());
    }

    public function testMeetingFormKeepsNativeTimeFieldsAndShowsReconnectPrompt(): void
    {
        $manager = $this->w['A']['manager'];
        // Connected, but the calendar permission was never granted.
        $this->connect($manager, ['scope' => 'https://www.googleapis.com/auth/userinfo.email']);

        $this->actAs($manager)->visit('/meetings/create');
        $html = $this->body();

        $this->assertStringContainsString('<input type="time" name="start_time"', $html);
        $this->assertStringContainsString('<input type="time" name="end_time"', $html);
        $this->assertStringContainsString('Reconnect Google Account', $html);
    }

    // ------------------------------------------------------------------
    // Zoom: the create request times out after Zoom may have created the meeting.
    // ------------------------------------------------------------------

    private const ZOOM_CREATE = 'post https://api.zoom.us/v2/users/me/meetings';
    private const ZOOM_LIST   = 'get https://api.zoom.us/v2/users/me/meetings';

    private function timeout(): \RuntimeException
    {
        return new \RuntimeException('cURL error 28: Operation timed out after 15001 milliseconds');
    }

    private function zoomSubmit(string $token = 'tok-1'): void
    {
        $this->actAs($this->w['A']['manager'])->submit('/meetings', [
            'title' => 'Zoom retry', 'company_id' => $this->w['A']['company']['id'], 'meeting_date' => date('Y-m-d', strtotime('+2 days')),
            'start_time' => '10:00', 'end_time' => '11:00', 'provider' => 'zoom', 'submission_token' => $token,
            'participant_user_ids' => [$this->w['A']['employee']['user_id']],
        ]);
    }

    /** The agenda the first create request sent, as Zoom would echo it back in its meeting list. */
    private function sentAgenda(): string
    {
        foreach ($this->http->callsTo('v2/users/me/meetings') as $c) {
            if ($c['method'] === 'post') {
                return json_decode($c['options']['body'], true)['agenda'];
            }
        }

        return '';
    }

    private function zoomCalls(string $method): int
    {
        return count(array_filter($this->http->callsTo('v2/users/me/meetings'), static fn ($c) => $c['method'] === $method));
    }

    private function zoomStart(array $createResponses): void
    {
        cache()->clean();
        $this->http->queue['zoom.us/oauth/token'] = [[200, ['access_token' => 'zt']]];
        $this->http->queue[self::ZOOM_CREATE]     = $createResponses;
    }

    public function testZoomTimeoutAfterSuccessIsReconciledOnRetryWithoutADuplicate(): void
    {
        $this->zoomStart([[0, $this->timeout()]]);

        // Attempt 1: Zoom created meeting 777 but the answer never arrived.
        $this->zoomSubmit();
        $this->assertSame(0, $this->countRows('meetings', ['title' => 'Zoom retry']), 'nothing is saved without a confirmed meeting');
        $this->assertFlash('error', 'may still have been created');
        $this->assertStringContainsString('[Ref ', $this->sentAgenda(), 'the request carried a reference Zoom keeps with the meeting');

        // Attempt 2 (same form, same token): Zoom's list now shows the meeting.
        $this->http->queue[self::ZOOM_LIST] = [[200, ['meetings' => [
            ['id' => 111, 'agenda' => 'Someone else', 'join_url' => 'https://zoom.us/j/111'],
            ['id' => 777, 'agenda' => $this->sentAgenda(), 'join_url' => 'https://zoom.us/j/777'],
        ]]]];
        $this->zoomSubmit();

        $m = $this->row('meetings', ['title' => 'Zoom retry']);
        $this->assertNotNull($m, 'saved on retry: ' . json_encode($this->flash('error')));
        $this->assertSame('777', $m['provider_meeting_id'], 'the meeting Zoom already had is adopted');
        $this->assertSame('https://zoom.us/j/777', $m['meeting_link']);
        $this->assertSame(1, $this->zoomCalls('post'), 'no second Zoom meeting is created');
    }

    public function testZoomRetryIsRefusedWhenZoomCannotBeCheckedSoNoDuplicateIsRisked(): void
    {
        $this->zoomStart([[0, $this->timeout()]]);
        $this->zoomSubmit();

        // E.g. the Zoom app lacks the list scope: the earlier attempt cannot be ruled in or out.
        $this->http->queue[self::ZOOM_LIST] = [[400, ['code' => 4711, 'message' => 'Invalid access token, does not contain scopes']]];
        $this->zoomSubmit();

        $this->assertSame(0, $this->countRows('meetings', ['title' => 'Zoom retry']));
        $this->assertSame(1, $this->zoomCalls('post'), 'no second create while the first is unresolved');
        $this->assertFlash('error', 'did not finish');
    }

    public function testZoomListWithoutAgendaFieldIsNotTrustedAsProofOfAbsence(): void
    {
        $this->zoomStart([[0, $this->timeout()]]);
        $this->zoomSubmit();

        $this->http->queue[self::ZOOM_LIST] = [[200, ['meetings' => [['id' => 5, 'topic' => 'x']]]]];
        $this->zoomSubmit();

        $this->assertSame(1, $this->zoomCalls('post'));
        $this->assertSame(0, $this->countRows('meetings', ['title' => 'Zoom retry']));
    }

    public function testZoomRetryCreatesNormallyWhenTheFirstAttemptReallyDidNotCreateAnything(): void
    {
        $this->zoomStart([[0, $this->timeout()], [201, ['id' => 888, 'join_url' => 'https://zoom.us/j/888']]]);
        $this->zoomSubmit();

        $this->http->queue[self::ZOOM_LIST] = [[200, ['meetings' => [['id' => 5, 'agenda' => 'unrelated', 'join_url' => 'u']]]]];
        $this->zoomSubmit();

        $this->assertSame('888', $this->row('meetings', ['title' => 'Zoom retry'])['provider_meeting_id']);
        $this->assertSame(2, $this->zoomCalls('post'));
    }

    public function testZoomMeetingAlreadyLinkedToAnErpMeetingIsNeverAdopted(): void
    {
        $this->zoomStart([[0, $this->timeout()], [201, ['id' => 889, 'join_url' => 'https://zoom.us/j/889']]]);
        $this->zoomSubmit();
        $agenda = $this->sentAgenda();

        // Meeting 777 carries our reference but already belongs to another ERP meeting.
        db_connect()->table('meetings')->insert([
            'company_id' => $this->w['A']['company']['id'], 'title' => 'Other', 'meeting_date' => date('Y-m-d'), 'created_by' => $this->w['A']['manager']['user_id'],
            'join_token' => bin2hex(random_bytes(20)), 'provider' => 'zoom', 'provider_meeting_id' => '777', 'sync_status' => 'synced',
        ]);
        $this->http->queue[self::ZOOM_LIST] = [[200, ['meetings' => [['id' => 777, 'agenda' => $agenda, 'join_url' => 'https://zoom.us/j/777']]]]];
        $this->zoomSubmit();

        $this->assertSame('889', $this->row('meetings', ['title' => 'Zoom retry'])['provider_meeting_id']);
    }

    public function testZoomClientErrorForgetsTheAttemptSoTheRetryDoesNotNeedAListCall(): void
    {
        $this->zoomStart([[400, ['code' => 300, 'message' => 'Invalid parameter']], [201, ['id' => 890, 'join_url' => 'https://zoom.us/j/890']]]);
        $this->zoomSubmit();
        $this->zoomSubmit();

        $this->assertSame(0, $this->zoomCalls('get'));
        $this->assertSame('890', $this->row('meetings', ['title' => 'Zoom retry'])['provider_meeting_id']);
    }

    public function testNormalZoomCreationMakesNoExtraListCall(): void
    {
        $this->zoomStart([[201, ['id' => 891, 'join_url' => 'https://zoom.us/j/891']]]);
        $this->zoomSubmit();

        $this->assertSame(0, $this->zoomCalls('get'));
        $this->assertSame(1, $this->zoomCalls('post'));
        $this->assertSame('891', $this->row('meetings', ['title' => 'Zoom retry'])['provider_meeting_id']);
    }
}
