<?php

namespace App\Models\Scopes;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (BranchContext::isBypassed() || ! BranchContext::id()) {
            return;
        }

        $builder->where($model->qualifyColumn('branch_id'), BranchContext::id());
    }
}
