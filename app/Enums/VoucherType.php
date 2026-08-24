<?php

namespace App\Enums;

enum VoucherType: string
{
    case Expense = 'expense';
    case Income = 'income';

    public function prefix(): string
    {
        return match ($this) {
            self::Expense => 'EXP',
            self::Income => 'INC',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Expense',
            self::Income => 'Income',
        };
    }
}
