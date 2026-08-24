<?php

namespace App\Models;

use App\Enums\ContributionBucket;
use App\Enums\ContributionFrequency;
use App\Enums\FinanceMode;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContributionProduct extends Model
{
    use BelongsToTenant, HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'bucket', 'description', 'finance_mode', 'frequency',
        'amount_is_fixed', 'minimum_amount_kobo', 'maximum_amount_kobo',
        'default_amount_kobo', 'starts_on', 'ends_on', 'is_locked',
        'notice_days', 'penalties_enabled', 'penalty_amount_kobo',
        'is_active', 'is_required', 'eligibility',
        'monipay_revenue_head_id', 'monipay_revenue_head_name',
        'monipay_settlement_bank_id', 'monipay_settlement_account_name',
        'monipay_settlement_account_no', 'monipay_settlement_bank_name',
    ];

    protected function casts(): array
    {
        return [
            'finance_mode' => FinanceMode::class,
            'bucket' => ContributionBucket::class,
            'frequency' => ContributionFrequency::class,
            'amount_is_fixed' => 'boolean',
            'is_locked' => 'boolean',
            'penalties_enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_required' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'eligibility' => 'array',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ContributionEnrollment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ContributionSchedule::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }
}
