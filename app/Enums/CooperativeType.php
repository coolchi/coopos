<?php

namespace App\Enums;

enum CooperativeType: string
{
    case SavingsThrift = 'savings_thrift';
    case Credit = 'credit';
    case Staff = 'staff';
    case Community = 'community';
    case Farmers = 'farmers';
    case Traders = 'traders';
    case Housing = 'housing';
    case Transport = 'transport';
    case Multipurpose = 'multipurpose';
    case Investment = 'investment';
    case Islamic = 'islamic';
    case Digital = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::SavingsThrift => 'Savings and Thrift',
            self::Credit => 'Credit Cooperative',
            self::Staff => 'Staff Cooperative',
            self::Community => 'Community Cooperative',
            self::Farmers => 'Farmers / Agricultural',
            self::Traders => 'Traders / Market',
            self::Housing => 'Housing Cooperative',
            self::Transport => 'Transport Cooperative',
            self::Multipurpose => 'Multipurpose Cooperative',
            self::Investment => 'Investment Cooperative',
            self::Islamic => 'Islamic Cooperative Society',
            self::Digital => 'Digital / Online Cooperative',
        };
    }
}
