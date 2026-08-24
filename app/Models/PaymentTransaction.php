<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Support\BelongsToBranch;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentTransaction extends Model
{
    use BelongsToBranch, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'member_id', 'provider', 'reference', 'provider_reference',
        'amount_kobo', 'currency', 'status', 'purpose', 'payable_type',
        'payable_id', 'payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
