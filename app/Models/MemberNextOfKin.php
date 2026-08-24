<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberNextOfKin extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $table = 'member_next_of_kin';

    protected $fillable = [
        'tenant_id', 'member_id', 'name', 'relationship', 'phone', 'address', 'allocation_percent',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
