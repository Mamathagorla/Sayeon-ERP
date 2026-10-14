<?php

use App\Modules\Auth\Services\LocalizationService;

// Display helpers for the organization/user localization profile (see
// LocalizationService). Views call these instead of hard-coding a date
// format, a time format, a timezone or a currency symbol.

if (! function_exists('erp_date')) {
    function erp_date(?string $value): string
    {
        return LocalizationService::instance()->formatDate($value);
    }
}

if (! function_exists('erp_time')) {
    function erp_time(?string $value): string
    {
        return LocalizationService::instance()->formatTime($value);
    }
}

if (! function_exists('erp_datetime')) {
    function erp_datetime(?string $value): string
    {
        return LocalizationService::instance()->formatDateTime($value);
    }
}

if (! function_exists('erp_money')) {
    function erp_money($amount, ?string $currency = null): string
    {
        return LocalizationService::instance()->formatMoney($amount, $currency);
    }
}

if (! function_exists('erp_number')) {
    // Same signature and default as number_format(), so the view sweep can
    // replace number_format() calls one-for-one without changing their meaning.
    function erp_number($value, int $decimals = 0): string
    {
        return LocalizationService::instance()->formatNumber($value, $decimals);
    }
}

if (! function_exists('erp_timezone')) {
    function erp_timezone(): string
    {
        return LocalizationService::instance()->timezone();
    }
}

if (! function_exists('erp_org_timezone')) {
    function erp_org_timezone(): string
    {
        return LocalizationService::instance()->orgTimezone();
    }
}

if (! function_exists('erp_locale')) {
    function erp_locale(): string
    {
        return LocalizationService::instance()->locale();
    }
}

if (! function_exists('erp_currency')) {
    function erp_currency(): string
    {
        return LocalizationService::instance()->currency();
    }
}

if (! function_exists('erp_meeting_when')) {
    /**
     * @return array{date: string, start: string, end: ?string, nextDay: bool, zone: string}
     */
    function erp_meeting_when(string $date, ?string $startTime, ?string $endTime, ?string $endDate, ?string $meetingTimezone): array
    {
        return LocalizationService::instance()->meetingWhen($date, $startTime, $endTime, $endDate, $meetingTimezone);
    }
}
