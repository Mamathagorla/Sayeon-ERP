<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Auth\Models\AppSettingModel;
use App\Modules\Auth\Services\LocalizationService;
use App\Modules\Meeting\Models\MeetingParticipantModel;
use App\Modules\Notification\Services\MailService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Builds and sends meeting invitation emails. Used for internal users and
 * for external invitees (name and email typed in on the meeting page), so
 * an external person never needs an ERP account to receive the invite.
 *
 * The Join button points at the provider's own link (Google Meet or Zoom),
 * which is the only link an outside guest can open. The internal join page
 * is never emailed because it requires a login.
 */
class MeetingInvitationService
{
    public const NO_LINK = 'no_link';

    /** Seconds the whole invitation batch may take before the rest is skipped. */
    private const TIME_BUDGET = 15;

    protected MailService $mail;
    protected MeetingParticipantModel $participantModel;

    public function __construct()
    {
        $this->mail             = new MailService();
        $this->participantModel = new MeetingParticipantModel();
    }

    /**
     * Every participant on a meeting who has an email address, internal users
     * (their account email) and external invitees alike. One entry per address,
     * so someone listed twice is invited only once.
     *
     * @return array<int, array{email: string, name: ?string}>
     */
    public function recipientsForMeeting(int $meetingId): array
    {
        $recipients = [];

        foreach ($this->participantModel->forMeeting($meetingId) as $participant) {
            $email = trim((string) $this->participantModel->displayEmail($participant));

            if ($email === '') {
                continue;
            }

            $recipients[strtolower($email)] ??= [
                'email' => $email,
                'name'  => $participant['user_name'] ?? $participant['external_name'] ?? null,
            ];
        }

        return array_values($recipients);
    }

    /**
     * Invites every recipient once, right after a meeting and its join link
     * exist. Sends nothing without a valid link. Each recipient gets their own email.
     *
     * @param array<int, array{email: string, name: ?string}> $recipients
     * @return array{status: string, sent: int, failed: int}
     */
    public function sendForMeeting(array $meeting, array $recipients): array
    {
        $result = ['status' => 'attempted', 'sent' => 0, 'failed' => 0];

        if ($recipients === []) {
            $result['status'] = 'no_recipients';

            return $result;
        }

        if ($this->validLink($meeting['meeting_link'] ?? null) === null) {
            $result['status'] = self::NO_LINK;

            return $result;
        }

        $started = microtime(true);

        foreach ($recipients as $index => $recipient) {
            // Invitations are best effort and must never hold the request open
            // past PHP's execution limit; whoever is left is reported as failed.
            if (microtime(true) - $started > self::TIME_BUDGET) {
                $result['failed'] += count($recipients) - $index;
                log_message('error', 'Meeting invitations stopped after ' . self::TIME_BUDGET . 's; ' . (count($recipients) - $index) . ' not sent.');

                break;
            }

            $status = $this->sendToParticipant($meeting, $recipient['email'], $recipient['name'] ?? null);

            if ($status === MailService::NOT_CONFIGURED) {
                // The same answer for every address, so stop rather than repeat it.
                $result['status'] = MailService::NOT_CONFIGURED;

                break;
            }

            $status === MailService::SENT ? $result['sent']++ : $result['failed']++;
        }

        return $result;
    }

    /**
     * Invites one person (an internal user or an external invitee who was just
     * added). Returns a MailService status, or NO_LINK when the meeting has no
     * valid join link yet.
     */
    public function sendToParticipant(array $meeting, string $email, ?string $name = null): string
    {
        $link = $this->validLink($meeting['meeting_link'] ?? null);

        if ($link === null) {
            return self::NO_LINK;
        }

        [$html, $text] = $this->body($meeting, $link, $name);

        return $this->mail->send($email, $this->subject($meeting), $html, $text);
    }

    /** Only a real http(s) URL may become the Join link, so nothing else can be linked from an email. */
    private function validLink(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }

    private function subject(array $meeting): string
    {
        return 'Meeting invitation: ' . $meeting['title'];
    }

    /** @return array{0: string, 1: string} The HTML body and its plain-text alternative. */
    private function body(array $meeting, string $link, ?string $name): array
    {
        $when     = $this->when($meeting);
        $provider = $this->providerLabel((string) ($meeting['provider'] ?? 'none'));
        $greeting = $name !== null && trim($name) !== '' ? 'Hello ' . trim($name) . ',' : 'Hello,';
        $agenda   = trim((string) ($meeting['agenda'] ?? ''));
        $brand    = (string) config('Email')->fromName;

        $rows = [
            ['Date', $when['date']],
            ['Start time', $when['start']],
            ['End time', $when['end']],
            ['Time zone', $when['zone']],
            ['Provider', $provider],
        ];

        if ($agenda !== '') {
            $rows[] = ['Agenda', $agenda];
        }

        return [
            $this->html((string) $meeting['title'], $greeting, $rows, $link, $brand),
            $this->text((string) $meeting['title'], $greeting, $rows, $link, $brand),
        ];
    }

    /**
     * Every value is escaped: the title, name and agenda come from users, and
     * the link was already checked to be an http(s) URL.
     *
     * @param array<int, array{0: string, 1: string}> $rows
     */
    private function html(string $title, string $greeting, array $rows, string $link, string $brand): string
    {
        $tableRows = '';

        foreach ($rows as [$label, $value]) {
            $tableRows .= '<tr>'
                . '<td style="padding:7px 0;width:110px;color:#8a929e;font-size:13px;vertical-align:top">' . esc($label) . '</td>'
                . '<td style="padding:7px 0;font-size:14px;font-weight:600;color:#1f242c">' . nl2br(esc($value)) . '</td>'
                . '</tr>';
        }

        $safeTitle = esc($title);
        $safeGreet = esc($greeting);
        $safeLink  = esc($link, 'attr');
        $safeText  = esc($link);
        $safeBrand = esc($brand);

        return '<!doctype html><html><body style="margin:0;padding:0;background:#f1f4f8;font-family:Arial,Helvetica,sans-serif;color:#1f242c">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f4f8;padding:24px 12px"><tr><td align="center">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border:1px solid #e6eaef;border-radius:12px">'
            . '<tr><td style="padding:24px 28px 4px">'
            . '<div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#8a929e;font-weight:700">Meeting invitation</div>'
            . '<h1 style="margin:6px 0 0;font-size:20px;line-height:1.3;color:#1f242c">' . $safeTitle . '</h1>'
            . '</td></tr>'
            . '<tr><td style="padding:14px 28px 4px;font-size:14px;line-height:1.5">' . $safeGreet . '<br>You have been invited to this meeting.</td></tr>'
            . '<tr><td style="padding:8px 28px 0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $tableRows . '</table></td></tr>'
            . '<tr><td style="padding:20px 28px 6px">'
            . '<a href="' . $safeLink . '" style="display:inline-block;background:#d62431;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:12px 24px;border-radius:999px">Join Meeting</a>'
            . '</td></tr>'
            . '<tr><td style="padding:6px 28px 4px;font-size:12px;color:#8a929e;line-height:1.5">If the button does not work, open this link: <a href="' . $safeLink . '" style="color:#cc1f2c;word-break:break-all">' . $safeText . '</a></td></tr>'
            . '<tr><td style="padding:16px 28px 22px;border-top:1px solid #eef1f5;font-size:12px;color:#8a929e;margin-top:12px">Sent by ' . $safeBrand . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /** @param array<int, array{0: string, 1: string}> $rows */
    private function text(string $title, string $greeting, array $rows, string $link, string $brand): string
    {
        $lines = [$greeting, '', 'You have been invited to this meeting: ' . $title, ''];

        foreach ($rows as [$label, $value]) {
            $lines[] = $label . ': ' . $value;
        }

        array_push($lines, '', 'Join Meeting: ' . $link, '', 'Sent by ' . $brand);

        return implode("\n", $lines);
    }

    /**
     * Date and times in the meeting's own timezone (the zone it was saved in),
     * labelled so recipients in other zones know what they are reading.
     *
     * @return array{date: string, start: string, end: string, zone: string}
     */
    private function when(array $meeting): array
    {
        $localization = LocalizationService::instance();
        $zoneName     = $meeting['timezone'] ?? null;

        if ($zoneName === null || ! AppSettingModel::isTimezone($zoneName)) {
            $zoneName = config('App')->appTimezone;
        }

        $zone  = new DateTimeZone($zoneName);
        $start = new DateTimeImmutable($meeting['meeting_date'] . ' ' . ($meeting['start_time'] ?: '00:00:00'), $zone);
        $end   = $meeting['end_time']
            ? new DateTimeImmutable(($meeting['end_date'] ?: $meeting['meeting_date']) . ' ' . $meeting['end_time'], $zone)
            : null;

        $pattern = $localization->timePattern();

        return [
            'date'  => $start->format($localization->dateFormat()),
            'start' => $meeting['start_time'] ? $start->format($pattern) : '—',
            'end'   => $end
                ? $end->format($pattern) . ($end->format('Y-m-d') !== $start->format('Y-m-d') ? ' (next day)' : '')
                : '—',
            'zone'  => $zoneName,
        ];
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'google_meet' => 'Google Meet',
            'zoom'        => 'Zoom',
            'none'        => 'Online meeting',
            default       => ucwords(str_replace('_', ' ', $provider)),
        };
    }
}
