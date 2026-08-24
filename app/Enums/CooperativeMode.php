<?php

namespace App\Enums;

enum CooperativeMode: string
{
    case General = 'general';
    case Islamic = 'islamic';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Islamic => 'Islamic',
            self::Hybrid => 'Hybrid',
        };
    }

    public function offersConventional(): bool
    {
        return $this !== self::Islamic;
    }

    public function offersIslamic(): bool
    {
        return $this !== self::General;
    }
}
