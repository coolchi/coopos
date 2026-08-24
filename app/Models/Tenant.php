<?php

namespace App\Models;

use App\Enums\CooperativeMode;
use App\Enums\CooperativeType;
use App\Enums\TenantStatus;
use App\Support\HasPublicUuid;
use App\Support\Tenancy;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasPublicUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'legal_name', 'slogan', 'registration_number', 'type', 'mode',
        'status', 'email', 'phone', 'address', 'city', 'state', 'lga',
        'logo_path', 'brand_color', 'theme', 'custom_domain', 'approved_at',
        'suspended_at', 'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => CooperativeType::class,
            'mode' => CooperativeMode::class,
            'status' => TenantStatus::class,
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function approvalWorkflows(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    public function appUrl(string $path = '/dashboard'): string
    {
        return Tenancy::url($this, $path);
    }

    public function host(): string
    {
        if (Tenancy::subdomainsEnabled()) {
            return $this->slug.'.'.Tenancy::centralDomain();
        }

        return Tenancy::centralDomain();
    }

    public function offersConventional(): bool
    {
        return $this->mode->offersConventional();
    }

    public function offersIslamic(): bool
    {
        return $this->mode->offersIslamic();
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}
