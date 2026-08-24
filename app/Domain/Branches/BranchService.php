<?php

namespace App\Domain\Branches;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BranchService
{
    public function ensureHeadquarters(Tenant $tenant): Branch
    {
        $existing = Branch::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_headquarters', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'CENTRAL BRANCH',
            'code' => 'HQ',
            'phone' => $tenant->phone,
            'email' => $tenant->email,
            'address' => $tenant->address,
            'city' => $tenant->city,
            'state' => $tenant->state,
            'is_headquarters' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Branch
    {
        return Branch::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code'] ?? Str::slug($data['name'])),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'is_headquarters' => false,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data): Branch
    {
        $branch->update([
            'name' => $data['name'] ?? $branch->name,
            'code' => strtoupper($data['code'] ?? $branch->code),
            'phone' => $data['phone'] ?? $branch->phone,
            'email' => $data['email'] ?? $branch->email,
            'address' => $data['address'] ?? $branch->address,
            'city' => $data['city'] ?? $branch->city,
            'state' => $data['state'] ?? $branch->state,
            'is_active' => $branch->is_headquarters
                ? true
                : (array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $branch->is_active),
        ]);

        return $branch->fresh();
    }

    public function setActive(Branch $branch, bool $active): Branch
    {
        if ($branch->is_headquarters && ! $active) {
            throw new InvalidArgumentException('The central branch cannot be deactivated.');
        }

        $branch->update(['is_active' => $active]);

        return $branch->fresh();
    }

    public function assignAdmin(Branch $branch, User $user): Branch
    {
        if ($user->tenant_id !== $branch->tenant_id || $user->type !== UserType::Staff) {
            throw new InvalidArgumentException('Branch admin must be staff of this cooperative.');
        }

        setPermissionsTeamId($branch->tenant_id);

        $user->forceFill(['branch_id' => $branch->id])->save();

        if (! $user->hasRole('Administrator') && ! $user->hasRole('Branch Admin')) {
            $user->syncRoles(['Branch Admin']);
        }

        $branch->update(['admin_user_id' => $user->id]);

        return $branch->fresh(['admin']);
    }

    public function unassignAdmin(Branch $branch): Branch
    {
        $branch->update(['admin_user_id' => null]);

        return $branch->fresh(['admin']);
    }

    public function assignStaff(Branch $branch, User $user): User
    {
        if ($user->tenant_id !== $branch->tenant_id || $user->type !== UserType::Staff) {
            throw new InvalidArgumentException('Only staff of this cooperative can be assigned to a branch.');
        }

        setPermissionsTeamId($branch->tenant_id);

        if ($user->hasRole('Administrator')) {
            throw new InvalidArgumentException('Central administrators are not assigned to a single branch.');
        }

        Branch::query()
            ->where('tenant_id', $branch->tenant_id)
            ->where('admin_user_id', $user->id)
            ->where('id', '!=', $branch->id)
            ->update(['admin_user_id' => null]);

        $user->forceFill(['branch_id' => $branch->id])->save();

        return $user->fresh();
    }

    public function removeStaff(Branch $branch, User $user): User
    {
        if ($user->branch_id !== $branch->id) {
            throw new InvalidArgumentException('That officer is not assigned to this branch.');
        }

        setPermissionsTeamId($branch->tenant_id);

        if ($branch->admin_user_id === $user->id) {
            $this->unassignAdmin($branch);
        }

        $hqId = $this->defaultBranchId();
        $user->forceFill(['branch_id' => $hqId === $branch->id ? null : $hqId])->save();

        return $user->fresh();
    }

    public function transferMember(Member $member, Branch $destination): Member
    {
        if ($member->tenant_id !== $destination->tenant_id) {
            throw new InvalidArgumentException('Members can only move within the same cooperative.');
        }

        $member->forceFill(['branch_id' => $destination->id])->save();

        return $member->fresh();
    }

    public function defaultBranchId(?int $preferred = null): int
    {
        if ($preferred) {
            $match = Branch::query()->whereKey($preferred)->first();
            if ($match) {
                return $match->id;
            }
        }

        $hq = Branch::query()->where('is_headquarters', true)->first()
            ?? Branch::query()->orderBy('id')->first();

        if (! $hq) {
            $hq = $this->ensureHeadquarters(tenant_or_fail());
        }

        return $hq->id;
    }

    public function findByUuidOrCode(?string $value): ?Branch
    {
        if (! $value) {
            return null;
        }

        return Branch::query()
            ->where(function ($q) use ($value) {
                $q->where('uuid', $value)->orWhere('code', strtoupper($value));
            })
            ->first();
    }
}
