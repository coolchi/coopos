<?php

namespace App\Models;

use App\Enums\ContributionFrequency;
use App\Enums\InterestMethod;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanProduct extends Model
{
    use BelongsToTenant, HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'minimum_amount_kobo', 'maximum_amount_kobo',
        'minimum_tenure_months', 'maximum_tenure_months', 'interest_method',
        'interest_rate_bps', 'processing_fee_kobo', 'insurance_fee_kobo',
        'grace_period_days', 'repayment_frequency', 'penalty_rate_bps',
        'guarantors_required', 'collateral_required', 'eligibility', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interest_method' => InterestMethod::class,
            'repayment_frequency' => ContributionFrequency::class,
            'collateral_required' => 'boolean',
            'is_active' => 'boolean',
            'eligibility' => 'array',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
