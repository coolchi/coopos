<?php

namespace App\Models;

use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'name', 'slug', 'description', 'monthly_price_kobo', 'yearly_price_kobo',
        'member_limit', 'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function allows(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}
