<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('branch_id')) {
                return;
            }

            $memberId = $model->getAttribute('member_id');
            if ($memberId) {
                $member = Member::query()->withoutGlobalScope(BranchScope::class)->find($memberId);
                if ($member?->branch_id) {
                    $model->setAttribute('branch_id', $member->branch_id);

                    return;
                }
            }

            if (BranchContext::id()) {
                $model->setAttribute('branch_id', BranchContext::id());
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
