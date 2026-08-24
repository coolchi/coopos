<?php

namespace App\Support;

final class DeviceDescriptor
{
    public static function label(?string $userAgent): string
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return 'Unknown device';
        }

        return self::browser($ua).' on '.self::platform($ua);
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') || str_contains($ua, 'Edge/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS/') => 'Firefox',
            str_contains($ua, 'CriOS/') || (str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Edg')) => 'Chrome',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome') => 'Safari',
            default => 'Browser',
        };
    }

    private static function platform(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'CrOS') => 'Chrome OS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };
    }
}
