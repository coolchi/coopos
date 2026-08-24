<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StaffImpersonationController extends Controller
{
    public function store(Request $request, string $tenant, Branch $branch)
    {
        $actor = $request->user();
        abort_unless($actor && ($actor->isCentralAdmin() || $actor->can('branches.manage')), 403);
        abort_if($request->session()->get('staff_impersonator_id'), 403, 'You are already logged in as another officer.');

        $data = $request->validate([
            'user_uuid' => ['nullable', 'uuid'],
        ]);

        try {
            $target = $this->resolveTarget($branch, $data['user_uuid'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['user_uuid' => $e->getMessage()]);
        }

        if ($target && ! $target->is($actor)) {
            return $this->impersonate($request, $branch, $actor, $target);
        }

        if ($actor->isCentralAdmin()) {
            return $this->enterBranchView($request, $branch);
        }

        throw ValidationException::withMessages([
            'user_uuid' => 'Assign a branch admin or officer before using Login as.',
        ]);
    }

    public function leave(Request $request)
    {
        $impersonatorId = $request->session()->pull('staff_impersonator_id');
        $request->session()->forget('staff_impersonator_name');

        abort_unless($impersonatorId, 403);

        $admin = User::query()->findOrFail($impersonatorId);
        abort_unless($admin->tenant_id === tenant()?->id, 403);

        auth()->login($admin);
        $request->session()->regenerate();
        setPermissionsTeamId($admin->tenant_id);

        return redirect()
            ->to(tenant_url(tenant(), '/dashboard'))
            ->with('success', 'You are back as '.$admin->name.'.');
    }

    private function impersonate(Request $request, Branch $branch, User $actor, User $target): RedirectResponse
    {
        $request->session()->put([
            'staff_impersonator_id' => $actor->id,
            'staff_impersonator_name' => $actor->name,
        ]);
        $request->session()->forget('branch_context_uuid');

        activity()
            ->causedBy($actor)
            ->performedOn($target)
            ->withProperties([
                'branch_uuid' => $branch->uuid,
                'branch_code' => $branch->code,
            ])
            ->log('logged in as branch staff');

        auth()->login($target);
        $request->session()->regenerate();
        setPermissionsTeamId($target->tenant_id);

        return redirect()
            ->to(tenant_url(tenant(), '/dashboard'))
            ->with('success', 'You are now viewing the cooperative as '.$target->name.'.');
    }

    private function enterBranchView(Request $request, Branch $branch): RedirectResponse
    {
        if (! $branch->is_active) {
            throw ValidationException::withMessages([
                'user_uuid' => 'That branch is not active.',
            ]);
        }

        $request->session()->put('branch_context_uuid', $branch->uuid);

        activity()
            ->causedBy($request->user())
            ->performedOn($branch)
            ->log('viewed cooperative as branch');

        return redirect()
            ->to(tenant_url(tenant(), '/dashboard'))
            ->with('success', 'You are now viewing '.$branch->name.' only.');
    }

    private function resolveTarget(Branch $branch, ?string $uuid): ?User
    {
        $branch->loadMissing('admin');

        $query = User::query()
            ->where('tenant_id', tenant()->id)
            ->where('type', UserType::Staff);

        if ($uuid) {
            $user = $query->where('uuid', $uuid)->first();
            if (! $user) {
                throw new InvalidArgumentException('That officer was not found.');
            }

            abort_unless(
                $user->branch_id === $branch->id || $branch->admin_user_id === $user->id,
                403,
                'That officer is not assigned to this branch.',
            );

            return $this->isImpersonatable($user) ? $user : null;
        }

        $admin = $branch->admin;
        if ($admin && $this->isImpersonatable($admin)) {
            return $admin;
        }

        return $query->where('branch_id', $branch->id)
            ->orderBy('name')
            ->get()
            ->first(fn (User $user) => $this->isImpersonatable($user));
    }

    private function isImpersonatable(User $user): bool
    {
        setPermissionsTeamId(tenant()->id);

        return $user->type === UserType::Staff
            && ! $user->isCentralAdmin()
            && ! $user->isPlatformAdmin();
    }
}
