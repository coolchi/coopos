<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
    case Resigned = 'resigned';
    case Deceased = 'deceased';
    case Rejected = 'rejected';

    public function canTransact(): bool
    {
        return $this === self::Active;
    }
}
