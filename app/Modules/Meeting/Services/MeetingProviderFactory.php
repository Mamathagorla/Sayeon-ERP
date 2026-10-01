<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Services\Providers\GoogleMeetProvider;
use App\Modules\Meeting\Services\Providers\MeetingProviderInterface;
use App\Modules\Meeting\Services\Providers\NullMeetingProvider;
use App\Modules\Meeting\Services\Providers\ZoomProvider;

class MeetingProviderFactory
{
    public static function make(string $provider): MeetingProviderInterface
    {
        return match ($provider) {
            'google_meet' => new GoogleMeetProvider(),
            'zoom'        => new ZoomProvider(),
            default       => new NullMeetingProvider(),
        };
    }
}
