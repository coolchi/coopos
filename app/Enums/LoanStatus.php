<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case PendingGuarantor = 'pending_guarantor';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Disbursed = 'disbursed';
    case Active = 'active';
    case Completed = 'completed';
    case Defaulted = 'defaulted';
    case WrittenOff = 'written_off';
}
