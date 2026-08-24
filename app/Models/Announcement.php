<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Support\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'tenant_id', 'branch_id', 'title', 'body', 'audience', 'is_pinned',
        'is_platform', 'created_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_platform' => 'boolean',
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    public function scopeForPortal(Builder $query, Member $member): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $q) use ($member) {
                $q->where('is_platform', true)
                    ->orWhere(function (Builder $tenant) use ($member) {
                        $tenant->where('tenant_id', $member->tenant_id)
                            ->where(function (Builder $audience) use ($member) {
                                $audience->where('audience', 'all')
                                    ->orWhereNull('audience');

                                if ($member->status === MemberStatus::Active) {
                                    $audience->orWhere('audience', 'active');
                                }

                                if ($member->branch_id) {
                                    $audience->orWhere(function (Builder $branch) use ($member) {
                                        $branch->where('audience', 'branch')
                                            ->where('branch_id', $member->branch_id);
                                    });
                                }
                            });
                    });
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');
    }
}
