<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\AppSettingModel;
use DateTimeImmutable;
use DateTimeZone;
use NumberFormatter;

/**
 * Resolves the effective localization for the current request and formats
 * values with it. Effective settings: the signed-in user's own timezone and
 * locale when they set one, otherwise the organization default.
 *
 * Storage convention (unchanged from before this feature):
 *   - DATE columns are calendar dates. They are never shifted by a timezone.
 *   - DATETIME columns (created_at, check-in/out, ...) are written by the app
 *     in its configured timezone (config('App')->appTimezone) and are
 *     converted to the viewer's timezone when displayed.
 *   - Meeting dates and times are wall-clock values in the meeting's own
 *     timezone (meetings.timezone; NULL means the app default) and are
 *     converted to the viewer's timezone when displayed.
 *
 * Request-scoped: one settings query and one user query per request. Call
 * reset() after saving settings so the same request sees the new values.
 */
class LocalizationService
{
    private static ?self $instance = null;

    private ?array $org = null;
    private ?array $user = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /** Organization-wide settings with the migration defaults filled in. */
    public function org(): array
    {
        if ($this->org !== null) {
            return $this->org;
        }

        $defaults = [
            'country_iso2' => 'IN',
            'currency_code' => 'INR',
            'timezone'      => config('App')->appTimezone,
            'locale'        => 'en_IN',
            'date_format'   => 'd/m/Y',
            'time_format'   => '12h',
        ];

        try {
            $rows   = (new AppSettingModel())->findAll();
            $stored = array_filter(array_column($rows, 'setting_value', 'setting_key'), static fn ($v) => $v !== null && $v !== '');
        } catch (\Throwable $e) {
            $stored = [];
        }

        $this->org = array_merge($defaults, $stored);

        return $this->org;
    }

    /** The signed-in user's timezone/locale preferences (empty outside a session). */
    private function userPrefs(): array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $this->user = [];
        try {
            $userId = session('userId');
            if ($userId) {
                $row = db_connect()->table('users')->select('timezone, locale')->where('id', (int) $userId)->get()->getRowArray();
                $this->user = $row ?? [];
            }
        } catch (\Throwable $e) {
            $this->user = [];
        }

        return $this->user;
    }

    /** The organization's timezone — the one new meetings are scheduled in. */
    public function orgTimezone(): string
    {
        $tz = $this->org()['timezone'];

        return AppSettingModel::isTimezone($tz) ? $tz : config('App')->appTimezone;
    }

    /** The timezone every display for the current viewer uses. */
    public function timezone(): string
    {
        $tz = $this->userPrefs()['timezone'] ?? null;

        return ($tz !== null && AppSettingModel::isTimezone($tz)) ? $tz : $this->orgTimezone();
    }

    public function locale(): string
    {
        $locale = $this->userPrefs()['locale'] ?? null;

        return ($locale !== null && array_key_exists($locale, AppSettingModel::LOCALES))
            ? $locale
            : $this->org()['locale'];
    }

    public function currency(): string
    {
        return $this->org()['currency_code'];
    }

    public function dateFormat(): string
    {
        $format = $this->org()['date_format'];

        return array_key_exists($format, AppSettingModel::DATE_FORMATS) ? $format : 'd/m/Y';
    }

    public function timePattern(): string
    {
        return $this->org()['time_format'] === '24h' ? 'H:i' : 'g:i A';
    }

    /** Date-only: never shifted. Datetime: converted from the app timezone. */
    public function formatDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (! $this->hasTimePart($value)) {
            return (new DateTimeImmutable($value))->format($this->dateFormat());
        }

        return $this->fromAppZone($value)->format($this->dateFormat());
    }

    /** Time-only values are wall clock as stored; datetimes are converted. */
    public function formatTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($this->isTimeOnly($value)) {
            return (new DateTimeImmutable('2000-01-01 ' . $value))->format($this->timePattern());
        }

        return $this->fromAppZone($value)->format($this->timePattern());
    }

    public function formatDateTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (! $this->hasTimePart($value)) {
            return $this->formatDate($value);
        }

        return $this->fromAppZone($value)->format($this->dateFormat() . ' ' . $this->timePattern());
    }

    /**
     * A meeting's wall-clock date and start/end times, converted from the
     * meeting's own timezone to the viewer's. Used by every meeting screen.
     *
     * @return array{date: string, start: string, end: ?string, nextDay: bool, zone: string}
     */
    public function meetingWhen(string $date, ?string $startTime, ?string $endTime, ?string $endDate, ?string $meetingTimezone): array
    {
        // A date with no start time has no instant to convert, so it is shown as entered.
        if (! $startTime) {
            return [
                'date'    => (new DateTimeImmutable($date))->format($this->dateFormat()),
                'start'   => '—',
                'end'     => null,
                'nextDay' => false,
                'zone'    => $this->timezone(),
            ];
        }

        $from = $meetingTimezone && AppSettingModel::isTimezone($meetingTimezone)
            ? new DateTimeZone($meetingTimezone)
            : new DateTimeZone(config('App')->appTimezone);
        $to = new DateTimeZone($this->timezone());

        $startAt = new DateTimeImmutable($date . ' ' . $startTime, $from);
        $endAt   = $endTime
            ? new DateTimeImmutable(($endDate ?: $date) . ' ' . $endTime, $from)
            : null;

        $startLocal = $startAt->setTimezone($to);
        $endLocal   = $endAt?->setTimezone($to);

        return [
            'date'    => $startLocal->format($this->dateFormat()),
            'start'   => $startLocal->format($this->timePattern()),
            'end'     => $endLocal ? $endLocal->format($this->timePattern()) : null,
            'nextDay' => $endLocal !== null && $endLocal->format('Y-m-d') !== $startLocal->format('Y-m-d'),
            'zone'    => $this->timezone(),
        ];
    }

    /** Money in the configured currency, formatted for the configured locale. */
    public function formatMoney($amount, ?string $currency = null): string
    {
        $code  = $currency ?: $this->currency();
        $value = (float) ($amount ?? 0);

        if (class_exists(NumberFormatter::class)) {
            $formatted = (new NumberFormatter($this->locale(), NumberFormatter::CURRENCY))->formatCurrency($value, $code);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $code . ' ' . number_format($value, 2);
    }

    /** A plain number (no currency symbol) with locale-correct grouping. */
    public function formatNumber($value, int $decimals = 2): string
    {
        $number = (float) ($value ?? 0);

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($this->locale(), NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $formatted = $formatter->format($number);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return number_format($number, $decimals);
    }

    private function appZone(): DateTimeZone
    {
        return new DateTimeZone(config('App')->appTimezone);
    }

    private function fromAppZone(string $value): DateTimeImmutable
    {
        return (new DateTimeImmutable($value, $this->appZone()))->setTimezone(new DateTimeZone($this->timezone()));
    }

    private function hasTimePart(string $value): bool
    {
        return (bool) preg_match('/\d{1,2}:\d{2}/', $value);
    }

    private function isTimeOnly(string $value): bool
    {
        return (bool) preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value);
    }
}
