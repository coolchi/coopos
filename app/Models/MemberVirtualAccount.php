<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberVirtualAccount extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'member_id', 'provider', 'wallet_id', 'customer_id', 'reference',
        'account_name', 'account_number', 'bank_name', 'bank_code', 'status',
        'meta', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
