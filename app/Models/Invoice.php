<?php

namespace App\Models;

use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'subscription_id', 'number', 'amount_kobo', 'status',
        'due_at', 'paid_at', 'provider', 'provider_reference',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
