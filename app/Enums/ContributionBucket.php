<?php

namespace App\Enums;

enum ContributionBucket: string
{
    case Share = 'share';
    case Savings = 'savings';
    case Mds = 'mds';
    case DevelopmentLevy = 'development_levy';
    case Insurance = 'insurance';
    case SpecialTarget = 'sts';
    case Agm = 'agm';
    case Project = 'project';
    case FoundationFee = 'foundation_fee';
    case RegistrationFee = 'registration_fee';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Share => 'Share',
            self::Savings => 'Savings',
            self::Mds => 'MDS',
            self::DevelopmentLevy => 'Dev. levy',
            self::Insurance => 'Insurance',
            self::SpecialTarget => 'STS',
            self::Agm => 'AGM',
            self::Project => 'Project',
            self::FoundationFee => 'F/Fee',
            self::RegistrationFee => 'Reg fee',
            self::Other => 'Other',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Share => 1,
            self::Savings => 2,
            self::Mds => 3,
            self::DevelopmentLevy => 4,
            self::Insurance => 5,
            self::SpecialTarget => 6,
            self::Agm => 7,
            self::Project => 8,
            self::FoundationFee => 9,
            self::RegistrationFee => 10,
            self::Other => 11,
        };
    }

    public function allowsWithdrawal(): bool
    {
        return $this === self::SpecialTarget;
    }

    public function ledgerCreditAccount(): string
    {
        return match ($this) {
            self::Share => '3000',
            self::RegistrationFee => '4100',
            self::DevelopmentLevy => '4120',
            self::Agm => '4140',
            self::FoundationFee => '4210',
            self::Other => '4210',
            default => '2100',
        };
    }
}
