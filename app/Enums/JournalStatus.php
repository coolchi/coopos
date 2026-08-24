<?php

namespace App\Enums;

enum JournalStatus: string
{
    case Posted = 'posted';
    case Reversed = 'reversed';
}
