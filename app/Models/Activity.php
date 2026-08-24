<?php

namespace App\Models;

use App\Support\DeviceDescriptor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $request = request();
            if (! $request instanceof Request) {
                return;
            }

            $userAgent = $request->userAgent();
            $ip = $request->ip();
            if (! $userAgent && ! $ip) {
                return;
            }

            $activity->properties = $activity->properties->merge([
                'ip' => $ip,
                'user_agent' => Str::limit((string) $userAgent, 400, ''),
                'device' => DeviceDescriptor::label($userAgent),
            ]);
        });
    }
}
