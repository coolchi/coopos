<?php

namespace App\Models;

use App\Enums\AppMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSetting extends Model
{
    protected $fillable = [
        'tenant_id', 'currency', 'app_mode', 'fiscal_year_start_month', 'fiscal_year_one_start_year', 'membership_number_prefix',
        'membership_number_next', 'membership_fee_kobo', 'require_membership_approval',
        'require_verified_bank_for_payout', 'guarantor_exposure_cap_kobo',
        'max_active_loans', 'max_debt_to_savings_bps',
        'loan_min_savings_percent', 'loan_min_shares_percent', 'loan_required_product_uuids',
        'loan_require_registration', 'loan_require_foundation', 'loan_require_agm', 'loan_require_dev_levy',
        'loan_check_attendance', 'loan_max_absences_per_year',
        'islamic_terminology',
        'notification_channels', 'extras',
    ];

    protected function casts(): array
    {
        return [
            'app_mode' => AppMode::class,
            'require_membership_approval' => 'boolean',
            'require_verified_bank_for_payout' => 'boolean',
            'loan_required_product_uuids' => 'array',
            'loan_require_registration' => 'boolean',
            'loan_require_foundation' => 'boolean',
            'loan_require_agm' => 'boolean',
            'loan_require_dev_levy' => 'boolean',
            'loan_check_attendance' => 'boolean',
            'islamic_terminology' => 'array',
            'notification_channels' => 'array',
            'extras' => 'array',
        ];
    }

    public function appMode(): AppMode
    {
        return $this->app_mode instanceof AppMode
            ? $this->app_mode
            : AppMode::tryFrom((string) $this->app_mode) ?? AppMode::Demo;
    }

    public function isDemo(): bool
    {
        return $this->appMode() === AppMode::Demo;
    }

    public function isLive(): bool
    {
        return $this->appMode() === AppMode::Live;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function nextMembershipNumber(): string
    {
        $number = $this->membership_number_next;
        $this->increment('membership_number_next');

        return sprintf('%s%05d', $this->membership_number_prefix ?: 'M', $number);
    }

    public function allowsManualPayments(): bool
    {
        $value = $this->extras['allow_manual_payments'] ?? true;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? true;
    }
}
