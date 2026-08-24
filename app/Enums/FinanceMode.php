<?php

namespace App\Enums;

enum FinanceMode: string
{
    case Conventional = 'conventional';
    case Islamic = 'islamic';

    public function label(): string
    {
        return match ($this) {
            self::Conventional => 'Conventional',
            self::Islamic => 'Non-interest',
        };
    }
}
