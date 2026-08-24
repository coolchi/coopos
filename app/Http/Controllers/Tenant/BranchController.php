<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Branches\BranchService;
use App\Enums\MemberStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class BranchController extends Controller
{
    public function index()
    {
        $this->authorizeView();

        $branches = $this->visibleBranches()
            ->with('admin')
            ->withCount([
                'members',
                'contributions',
                'staff' => fn ($q) => $q->where('type', UserType::Staff),
            ])
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get();

        $contributionTotals = Contribution::query()
            ->selectRaw('branch_id, coalesce(sum(amount_kobo), 0) as total_kobo')
            ->groupBy('branch_id')
            ->pluck('total_kobo', 'branch_id');

        return Inertia::render('Tenant/Branches/Index', [
            'can_manage' => $this->canManage(),
            'can_login_as' => $this->canLoginAs(),
            'staff' => $this->canManage()
                ? User::query()->where('tenant_id', tenant()->id)->where('type', UserType::Staff)
                    ->orderBy('name')->get(['uuid', 'name', 'email', 'branch_id'])
                : [],
            'states' => config('coopos.nigeria_states'),
            'summary' => [
                'branches' => $branches->count(),
                'members' => $branches->sum('members_count'),
                'staff' => $branches->sum('staff_count'),
                'payments_total' => money((int) $contributionTotals->sum()),
            ],
            'branches' => $branches->map(fn (Branch $b) => $this->card($b, (int) ($contributionTotals[$b->id] ?? 0))),
        ]);
    }

    public function store(Request $request, BranchService $service)
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validated($request);
        $branch = $service->create($data);

        if (! empty($data['admin_user_uuid'])) {
            $service->assignAdmin($branch, $this->staffByUuid($data['admin_user_uuid']));
        }

        return back()->with('success', 'Branch created.');
    }

    public function show(string $tenant, Branch $branch)
    {
        $this->authorizeBranch($branch);

        $previous = BranchContext::current();
        BranchContext::set($branch);

        try {
            $members = Member::query()->latest()->limit(8)->get();
            $payments = Contribution::query()->with('member', 'product')->latest()->limit(8)->get();
            $withdrawals = WithdrawalRequest::query()->with('member', 'product')->latest()->limit(8)->get();
            $staffHere = User::query()
                ->where('tenant_id', tenant()->id)
                ->where('type', UserType::Staff)
                ->where('branch_id', $branch->id)
                ->orderBy('name')
                ->get();

            setPermissionsTeamId(tenant()->id);

            $assignableStaff = $this->canManage()
                ? User::query()
                    ->where('tenant_id', tenant()->id)
                    ->where('type', UserType::Staff)
                    ->where(function ($q) use ($branch) {
                        $q->whereNull('branch_id')->orWhere('branch_id', '!=', $branch->id);
                    })
                    ->with('branch')
                    ->orderBy('name')
                    ->get()
                    ->reject(fn (User $u) => $u->isCentralAdmin())
                    ->values()
                : collect();

            return Inertia::render('Tenant/Branches/Show', [
                'can_manage' => $this->canManage(),
                'can_login_as' => $this->canLoginAs(),
                'staff' => $this->canManage()
                    ? User::query()->where('tenant_id', tenant()->id)->where('type', UserType::Staff)
                        ->orderBy('name')->get(['uuid', 'name', 'email', 'branch_id'])
                    : [],
                'assignable_staff' => $assignableStaff->map(fn (User $u) => [
                    'uuid' => $u->uuid,
                    'name' => $u->branch ? $u->name.' ('.$u->branch->name.')' : $u->name,
                    'email' => $u->email,
                    'branch' => $u->branch?->name,
                ]),
                'other_branches' => Branch::query()
                    ->whereKeyNot($branch->id)
                    ->orderByDesc('is_headquarters')
                    ->orderBy('name')
                    ->get(['uuid', 'name', 'code']),
                'states' => config('coopos.nigeria_states'),
                'products' => ContributionController::paymentProductOptions(),
                ...ContributionController::paymentCatalog(),
                'member_options' => Member::query()->where('status', MemberStatus::Active)->orderBy('first_name')
                    ->get(['uuid', 'first_name', 'last_name', 'membership_number']),
                'branch' => [
                    ...$this->card($branch->load('admin'), (int) Contribution::query()->sum('amount_kobo')),
                    'phone' => $branch->phone,
                    'email' => $branch->email,
                    'address' => $branch->address,
                    'city' => $branch->city,
                    'state' => $branch->state,
                    'is_active' => $branch->is_active,
                ],
                'kpis' => [
                    'members' => Member::query()->count(),
                    'active_members' => Member::query()->where('status', MemberStatus::Active)->count(),
                    'payments_total' => money((int) Contribution::query()->sum('amount_kobo')),
                    'pending_withdrawals' => WithdrawalRequest::query()->where('status', 'pending')->count(),
                    'staff' => $staffHere->count(),
                ],
                'staff_members' => $staffHere->map(fn (User $u) => [
                    'uuid' => $u->uuid,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->getRoleNames()->values(),
                    'is_admin' => $branch->admin_user_id === $u->id,
                ]),
                'members' => $members->map(fn (Member $m) => [
                    'uuid' => $m->uuid,
                    'membership_number' => $m->membership_number,
                    'name' => $m->fullName(),
                    'phone' => $m->phone,
                    'status' => $m->status->value,
                ]),
                'payments' => $payments->map(fn (Contribution $c) => [
                    'uuid' => $c->uuid,
                    'member' => $c->member?->fullName(),
                    'product' => $c->product?->name,
                    'amount' => money($c->amount_kobo),
                    'paid_on' => $c->paid_on?->toDateString(),
                    'channel' => $c->channel,
                ]),
                'withdrawals' => $withdrawals->map(fn (WithdrawalRequest $w) => [
                    'uuid' => $w->uuid,
                    'member' => $w->member?->fullName(),
                    'product' => $w->product?->name,
                    'amount' => money($w->amount_kobo),
                    'status' => $w->status,
                ]),
            ]);
        } finally {
            BranchContext::set($previous);
        }
    }

    public function update(Request $request, string $tenant, Branch $branch, BranchService $service)
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validated($request, $branch);
        $service->update($branch, $data);

        if (array_key_exists('admin_user_uuid', $data)) {
            if ($data['admin_user_uuid']) {
                $service->assignAdmin($branch->fresh(), $this->staffByUuid($data['admin_user_uuid']));
            } else {
                $service->unassignAdmin($branch->fresh());
            }
        }

        return back()->with('success', 'Branch updated.');
    }

    public function setStatus(Request $request, string $tenant, Branch $branch, BranchService $service)
    {
        abort_unless($this->canManage(), 403);
        abort_unless($request->has('is_active'), 422);

        try {
            $service->setActive($branch, $request->boolean('is_active'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $request->boolean('is_active') ? 'Branch activated.' : 'Branch deactivated.');
    }

    public function assignAdmin(Request $request, string $tenant, Branch $branch, BranchService $service)
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'admin_user_uuid' => ['required', 'uuid'],
        ]);

        $service->assignAdmin($branch, $this->staffByUuid($data['admin_user_uuid']));

        return back()->with('success', 'Branch admin assigned.');
    }

    public function assignStaff(Request $request, string $tenant, Branch $branch, BranchService $service)
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'staff_user_uuid' => ['required', 'uuid'],
        ]);

        try {
            $service->assignStaff($branch, $this->staffByUuid($data['staff_user_uuid']));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['staff_user_uuid' => $e->getMessage()]);
        }

        return back()->with('success', 'Staff assigned to this branch.');
    }

    public function removeStaff(string $tenant, Branch $branch, User $user, BranchService $service)
    {
        abort_unless($this->canManage(), 403);
        abort_unless($user->tenant_id === tenant()->id && $user->type === UserType::Staff, 404);

        try {
            $service->removeStaff($branch, $user);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Staff removed from this branch.');
    }

    public function transferMember(Request $request, string $tenant, Branch $branch, BranchService $service)
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'member_uuid' => ['required', 'uuid'],
            'destination_uuid' => ['required', 'uuid'],
        ]);

        $member = Member::query()->where('uuid', $data['member_uuid'])->firstOrFail();
        abort_unless($member->branch_id === $branch->id, 404);

        $destination = Branch::query()->where('uuid', $data['destination_uuid'])->firstOrFail();

        try {
            $service->transferMember($member, $destination);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['destination_uuid' => $e->getMessage()]);
        }

        return back()->with('success', $member->fullName().' moved to '.$destination->name.'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'required', 'string', 'max:16',
                Rule::unique('branches', 'code')->where(fn ($q) => $q->where('tenant_id', tenant()->id))->ignore($branch?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'admin_user_uuid' => ['nullable', 'uuid'],
        ]);
    }

    private function card(Branch $branch, int $paymentsKobo): array
    {
        return [
            'uuid' => $branch->uuid,
            'name' => $branch->name,
            'code' => $branch->code,
            'is_headquarters' => $branch->is_headquarters,
            'is_active' => $branch->is_active,
            'city' => $branch->city,
            'state' => $branch->state,
            'admin' => $branch->admin ? [
                'uuid' => $branch->admin->uuid,
                'name' => $branch->admin->name,
                'email' => $branch->admin->email,
            ] : null,
            'phone' => $branch->phone,
            'members_count' => $branch->members_count ?? $branch->members()->count(),
            'staff_count' => $branch->staff_count ?? $branch->staff()->where('type', UserType::Staff)->count(),
            'payments_count' => $branch->contributions_count ?? $branch->contributions()->count(),
            'payments_total' => money($paymentsKobo),
            'can_login_as' => $this->canLoginAs() && (bool) $branch->admin_user_id,
        ];
    }

    private function visibleBranches()
    {
        $query = Branch::query();

        if (BranchContext::isConstrained()) {
            $query->whereKey(BranchContext::id());
        }

        return $query;
    }

    private function authorizeView(): void
    {
        $user = request()->user();
        abort_unless($user && ($this->canManage() || $user->branch_id || $user->isBranchAdmin()), 403);
    }

    private function authorizeBranch(Branch $branch): void
    {
        abort_unless($branch->tenant_id === tenant()->id, 404);

        if ($this->canManage()) {
            return;
        }

        abort_unless(request()->user()?->branch_id === $branch->id, 403);
    }

    private function canManage(): bool
    {
        $user = request()->user();

        return (bool) ($user?->isCentralAdmin() || $user?->can('branches.manage'));
    }

    private function canLoginAs(): bool
    {
        return $this->canManage() && ! request()->session()->get('staff_impersonator_id');
    }

    private function staffByUuid(string $uuid): User
    {
        return User::query()
            ->where('tenant_id', tenant()->id)
            ->where('type', UserType::Staff)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
