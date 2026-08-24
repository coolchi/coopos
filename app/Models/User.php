<?php

namespace App\Models;

use App\Enums\UserType;
use App\Models\Scopes\TenantScope;
use App\Support\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicUuid, HasRoles, LogsActivity, Notifiable;

    protected $fillable = [
        'uuid', 'tenant_id', 'branch_id', 'type', 'is_platform_admin', 'name', 'email', 'phone',
        'password', 'avatar_path', 'email_verified_at', 'phone_verified_at',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'type' => UserType::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email', 'phone', 'type'])->logOnlyDirty();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withoutGlobalScope(TenantScope::class);
    }

    public function isCentralAdmin(): bool
    {
        if ($this->isPlatformAdmin()) {
            return false;
        }

        return $this->hasRole('Administrator');
    }

    public function isBranchAdmin(): bool
    {
        return $this->hasRole('Branch Admin');
    }

    public function isBranchScoped(): bool
    {
        return (bool) $this->branch_id && ! $this->isCentralAdmin();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    public function accessibleBranches()
    {
        if ($this->type !== UserType::Staff || ! \App\Support\TenantContext::id()) {
            return collect();
        }

        $query = Branch::query()->where('is_active', true)->orderByDesc('is_headquarters')->orderBy('name');

        if ($this->isBranchScoped()) {
            return $query->whereKey($this->branch_id)->get();
        }

        if ($this->isCentralAdmin()) {
            return $query->get();
        }

        return $this->branch_id ? $query->whereKey($this->branch_id)->get() : collect();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin || $this->type === UserType::Platform;
    }

    public function isMemberUser(): bool
    {
        return $this->type === UserType::Member;
    }

    public function homeUrl(): string
    {
        if ($this->isPlatformAdmin() && ! session('impersonator_id')) {
            return \App\Support\Tenancy::centralUrl('/platform');
        }

        $tenant = $this->tenant;

        if ($tenant) {
            return $this->isMemberUser()
                ? tenant_url($tenant, '/portal')
                : tenant_url($tenant, '/dashboard');
        }

        return route('home', absolute: false);
    }
}
