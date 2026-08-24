<?php

namespace App\Enums;

enum ShariahStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case RequiresChanges = 'requires_changes';
    case Suspended = 'suspended';
}
