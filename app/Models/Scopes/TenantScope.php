<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RuntimeException;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::isBypassed()) {
            return;
        }

        $tenantId = TenantContext::id();

        if (! $tenantId) {
            throw new RuntimeException('Tenant scope applied without a tenant. Refusing to query '.$model->getTable().'.');
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
