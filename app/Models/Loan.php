<?php

namespace App\Models;

use App\Enums\ContributionFrequency;
use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Support\BelongsToBranch;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use BelongsToBranch, BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'member_id', 'loan_product_id', 'loan_application_id',
        'account_number', 'principal_kobo', 'interest_kobo', 'fees_kobo',
        'total_repayable_kobo', 'outstanding_kobo', 'tenure_months',
        'interest_method', 'interest_rate_bps', 'repayment_frequency',
        'status', 'disbursed_on', 'maturity_on', 'financial_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => ContributionFrequency::class,
            'disbursed_on' => 'date',
            'maturity_on' => 'date',
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

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }
}
