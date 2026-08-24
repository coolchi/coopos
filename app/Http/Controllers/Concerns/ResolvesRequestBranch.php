<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Support\BranchContext;

trait ResolvesRequestBranch
{
    protected function requestBranch(?string $uuid): ?Branch
    {
        if (BranchContext::isConstrained()) {
            return Branch::query()->find(BranchContext::id());
        }

        if ($uuid && $uuid !== 'all') {
            return Branch::query()->where('uuid', $uuid)->first();
        }

        return null;
    }

    /**
     * @return list<array{uuid: string, name: string}>
     */
    protected function branchOptions(): array
    {
        return Branch::query()->where('is_active', true)->orderByDesc('is_headquarters')->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn (Branch $b) => ['uuid' => $b->uuid, 'name' => $b->name])
            ->all();
    }

    /**
     * @return array{uuid: string, name: string}|null
     */
    protected function branchPayload(?Branch $branch): ?array
    {
        if (! $branch) {
            return null;
        }

        return ['uuid' => $branch->uuid, 'name' => $branch->name];
    }
}
