<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'subscription_plan_id', 'status', 'interval',
        'trial_ends_at', 'starts_at', 'ends_at', 'cancelled_at', 'grace_ends_at',
        'paystack_authorization_code', 'paystack_customer_code',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'grace_ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)
            || ($this->status === SubscriptionStatus::PastDue && $this->grace_ends_at?->isFuture());
    }
}
