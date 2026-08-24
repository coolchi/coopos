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

class MurabahaFinancing extends Model
{
    use BelongsToBranch, BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'member_id', 'islamic_financing_product_id', 'account_number',
        'asset_description', 'supplier', 'purchase_cost_kobo', 'profit_kobo',
        'sale_price_kobo', 'down_payment_kobo', 'outstanding_kobo', 'tenure_months',
        'status', 'invoice_path', 'agreement_on', 'disbursed_on', 'sale_frozen',
        'financial_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'agreement_on' => 'date',
            'disbursed_on' => 'date',
            'sale_frozen' => 'boolean',
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

    public function asset(): HasOne
    {
        return $this->hasOne(MurabahaAsset::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(MurabahaInstallment::class);
    }
}
