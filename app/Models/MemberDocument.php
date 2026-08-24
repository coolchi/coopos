<?php

namespace App\Models;

use App\Support\BelongsToTenant;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberDocument extends Model
{
    use BelongsToTenant, HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'member_id', 'category', 'name', 'path', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
