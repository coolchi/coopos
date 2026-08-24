<?php

namespace App\Models;

use App\Enums\ContributionFrequency;
use App\Enums\IslamicStructure;
use App\Enums\ShariahStatus;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IslamicFinancingProduct extends Model
{
    use BelongsToTenant, HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'structure', 'minimum_amount_kobo',
        'maximum_amount_kobo', 'minimum_tenure_months', 'maximum_tenure_months',
        'admin_fee_kobo', 'default_profit_bps', 'repayment_frequency',
        'guarantors_required', 'is_active', 'shariah_status',
        'monipay_revenue_head_id', 'monipay_revenue_head_name',
        'monipay_settlement_bank_id', 'monipay_settlement_account_name',
        'monipay_settlement_account_no', 'monipay_settlement_bank_name',
    ];

    protected function casts(): array
    {
        return [
            'structure' => IslamicStructure::class,
            'repayment_frequency' => ContributionFrequency::class,
            'is_active' => 'boolean',
            'shariah_status' => ShariahStatus::class,
        ];
    }

    public function shariahApprovals(): HasMany
    {
        return $this->hasMany(ShariahApproval::class);
    }

    public function qardHasanFinancings(): HasMany
    {
        return $this->hasMany(QardHasanFinancing::class);
    }

    public function murabahaFinancings(): HasMany
    {
        return $this->hasMany(MurabahaFinancing::class);
    }
}
