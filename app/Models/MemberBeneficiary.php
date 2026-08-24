<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberBeneficiary extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'member_id', 'name', 'relationship', 'phone', 'purpose', 'allocation_percent',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
