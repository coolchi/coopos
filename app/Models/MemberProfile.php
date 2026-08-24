<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'member_id', 'residential_address', 'state', 'lga',
        'bvn_encrypted', 'nin_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'bvn_encrypted' => 'encrypted',
            'nin_encrypted' => 'encrypted',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
