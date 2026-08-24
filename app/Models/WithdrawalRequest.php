<?php

namespace App\Models;

use App\Support\BelongsToBranch;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use BelongsToBranch, BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'member_id', 'contribution_product_id', 'type', 'amount_kobo',
        'fee_kobo', 'status', 'reason', 'member_bank_account_id', 'reviewed_by',
        'reviewed_at', 'review_notes', 'financial_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ContributionProduct::class, 'contribution_product_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(MemberBankAccount::class, 'member_bank_account_id');
    }
}
