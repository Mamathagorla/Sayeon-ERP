<?php

namespace App\Modules\Meeting\Services\Providers;

/**
 * What every provider call (create/update/delete) returns — a plain
 * value object so MeetingController never has to know which provider it
 * called or parse a provider-specific response shape itself.
 */
class MeetingProviderResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $providerMeetingId,
        public readonly ?string $joinUrl,
        public readonly ?string $errorMessage,
    ) {
    }

    public static function ok(?string $providerMeetingId, ?string $joinUrl): self
    {
        return new self(true, $providerMeetingId, $joinUrl, null);
    }

    public static function fail(string $errorMessage): self
    {
        return new self(false, null, null, $errorMessage);
    }
}
