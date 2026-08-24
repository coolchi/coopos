<?php

namespace App\Enums;

enum AppMode: string
{
    case Live = 'live';
    case Demo = 'demo';

    public function label(): string
    {
        return match ($this) {
            self::Live => 'Live',
            self::Demo => 'Demo',
        };
    }

    public function allowsSeeding(): bool
    {
        return $this === self::Demo;
    }
}
