<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Support\BelongsToBranch;
use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QardHasanFinancing extends Model
{
    use BelongsToBranch, BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'member_id', 'islamic_financing_product_id', 'account_number',
        'principal_kobo', 'admin_fee_kobo', 'total_repayable_kobo', 'outstanding_kobo',
        'tenure_months', 'status', 'purpose', 'disbursed_on', 'maturity_on',
        'financial_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
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
        return $this->belongsTo(IslamicFinancingProduct::class, 'islamic_financing_product_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(QardHasanInstallment::class);
    }
}
