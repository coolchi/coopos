<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Support\BelongsToBranch;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanApplication extends Model
{
    use BelongsToBranch, BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'member_id', 'loan_product_id', 'amount_kobo', 'tenure_months',
        'estimated_repayment_kobo', 'purpose', 'status', 'eligibility_snapshot',
        'rejection_reason', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'eligibility_snapshot' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }
}
