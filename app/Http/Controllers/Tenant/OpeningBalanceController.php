<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Savings\ContributionService;
use App\Domain\Savings\DefaultSavingsCatalog;
use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Contribution;
use App\Models\ContributionProduct;
use App\Models\Member;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class OpeningBalanceController extends Controller
{
    public function index(Request $request)
    {
        $locked = $this->isLocked();
        $user = $request->user();

        if ($locked && ! $user?->can('settings.manage')) {
            return redirect()
                ->route('tenant.contributions.index', ['tenant' => tenant()?->slug])
                ->with('error', 'Brought forward is locked. Cutover is complete.');
        }

        app(DefaultSavingsCatalog::class)->ensure();

        [$start] = $this->fiscalYear($request->query('year'));
        $branch = $this->selectedBranch($request->query('branch'));
        $needsBranch = (bool) $user?->isCentralAdmin() && ! $branch;
        $products = ContributionProduct::query()->where('is_active', true)->get()
            ->sortBy(fn (ContributionProduct $p) => ($p->bucket?->sortOrder() ?? 99).$p->name)
            ->values();
        $members = $needsBranch
            ? collect()
            : Member::query()
                ->with('branch')
                ->where('status', MemberStatus::Active)
                ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
                ->orderBy('membership_number')
                ->get();

        $posted = collect();
        if (! $needsBranch && $members->isNotEmpty()) {
            $posted = Contribution::query()
                ->where('channel', 'opening')
                ->whereDate('paid_on', $start->toDateString())
                ->whereIn('member_id', $members->pluck('id'))
                ->selectRaw('member_id, contribution_product_id, sum(amount_kobo) as total_kobo')
                ->groupBy('member_id', 'contribution_product_id')
                ->get()
                ->keyBy(fn ($row) => $row->member_id.':'.$row->contribution_product_id);
        }

        $postedCount = $posted->filter(fn ($row) => (int) $row->total_kobo > 0)->count();
        $lockMeta = $this->lockMeta();

        return Inertia::render('Tenant/Savings/OpeningBalances', [
            'year' => (int) $start->year,
            'as_of' => $start->toDateString(),
            'locked' => $locked,
            'locked_at' => $lockMeta['locked_at'],
            'locked_by' => $lockMeta['locked_by'],
            'posted_count' => $postedCount,
            'can_lock' => ! $locked && (bool) $user?->can('contributions.manage'),
            'can_unlock' => $locked && (bool) $user?->can('settings.manage'),
            'can_filter_branches' => (bool) $user?->isCentralAdmin(),
            'needs_branch' => $needsBranch,
            'branch' => $branch ? ['uuid' => $branch->uuid, 'name' => $branch->name] : null,
            'branches' => $this->branchFilterOptions($user),
            'columns' => $products->map(fn (ContributionProduct $p) => [
                'uuid' => $p->uuid,
                'name' => $p->name,
                'label' => $p->bucket?->label() ?? $p->name,
            ]),
            'rows' => $members->map(function (Member $member) use ($products, $posted) {
                $cells = [];
                $postedFlags = [];
                foreach ($products as $product) {
                    $kobo = (int) ($posted[$member->id.':'.$product->id]->total_kobo ?? 0);
                    $cells[$product->uuid] = round($kobo / 100, 2);
                    $postedFlags[$product->uuid] = $kobo > 0;
                }

                return [
                    'uuid' => $member->uuid,
                    'number' => $member->membership_number,
                    'name' => $member->fullName(),
                    'branch' => $member->branch?->name,
                    'cells' => $cells,
                    'posted' => $postedFlags,
                ];
            }),
        ]);
    }

    public function store(Request $request, ContributionService $service)
    {
        $this->abortIfLocked();

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.member_uuid' => ['required', 'uuid'],
            'entries.*.product_uuid' => ['required', 'uuid'],
            'entries.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        [$start] = $this->fiscalYear((string) $data['year']);
        $members = Member::query()->whereIn('uuid', collect($data['entries'])->pluck('member_uuid'))->get()->keyBy('uuid');
        $products = ContributionProduct::query()->whereIn('uuid', collect($data['entries'])->pluck('product_uuid'))->get()->keyBy('uuid');

        $posted = 0;
        try {
            DB::transaction(function () use ($data, $members, $products, $service, $start, &$posted) {
                foreach ($data['entries'] as $entry) {
                    $amount = (float) $entry['amount'];
                    if ($amount <= 0) {
                        continue;
                    }
                    $member = $members->get($entry['member_uuid']);
                    $product = $products->get($entry['product_uuid']);
                    if (! $member || ! $product) {
                        continue;
                    }
                    $already = Contribution::query()
                        ->where('member_id', $member->id)
                        ->where('contribution_product_id', $product->id)
                        ->where('channel', 'opening')
                        ->whereDate('paid_on', $start->toDateString())
                        ->exists();
                    if ($already) {
                        continue;
                    }
                    $service->recordPayment(
                        $member,
                        $product,
                        naira_to_kobo($amount),
                        'opening',
                        paidOn: $start->toDateString(),
                        skipLimits: true,
                    );
                    $posted++;
                }
            });
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $posted ? "{$posted} brought-forward balances saved." : 'No new brought-forward balances to save.');
    }

    public function lock(Request $request)
    {
        $this->abortIfLocked();

        $extras = tenant()->settings->extras ?? [];
        $extras['opening_balances_locked_at'] = now()->toIso8601String();
        $extras['opening_balances_locked_by'] = $request->user()?->id;
        tenant()->settings->update(['extras' => $extras]);

        return back()->with('success', 'Brought forward locked. Cutover is complete.');
    }

    public function unlock(Request $request)
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        if (! $this->isLocked()) {
            return back()->with('error', 'Brought forward is already open.');
        }

        $data = $request->validate([
            'access_code' => ['required', 'string'],
        ], [
            'access_code.required' => 'Enter the access code.',
        ]);

        $this->assertAccessCode($data['access_code']);

        $extras = tenant()->settings->extras ?? [];
        unset($extras['opening_balances_locked_at'], $extras['opening_balances_locked_by']);
        tenant()->settings->update(['extras' => $extras]);

        return back()->with('success', 'Brought forward unlocked for cutover corrections.');
    }

    private function abortIfLocked(): void
    {
        if ($this->isLocked()) {
            abort(403, 'Brought forward is locked after go-live.');
        }
    }

    private function isLocked(): bool
    {
        return filled(tenant()?->settings?->extras['opening_balances_locked_at'] ?? null);
    }

    /**
     * @return array{locked_at: string|null, locked_by: string|null}
     */
    private function lockMeta(): array
    {
        $extras = tenant()?->settings?->extras ?? [];
        $lockedAt = $extras['opening_balances_locked_at'] ?? null;
        $lockedById = $extras['opening_balances_locked_by'] ?? null;
        $lockedBy = null;
        if ($lockedById) {
            $lockedBy = User::query()->find($lockedById)?->name;
        }

        return [
            'locked_at' => $lockedAt ? Carbon::parse($lockedAt)->toDayDateTimeString() : null,
            'locked_by' => $lockedBy,
        ];
    }

    private function assertAccessCode(string $code): void
    {
        $hash = tenant()?->settings?->extras['transaction_access_code_hash'] ?? null;
        $valid = $hash
            ? Hash::check($code, $hash)
            : hash_equals((string) config('coopos.default_transaction_access_code', '1234'), $code);

        if (! $valid) {
            throw ValidationException::withMessages([
                'access_code' => 'Invalid access code.',
            ]);
        }
    }

    private function selectedBranch(?string $uuid): ?Branch
    {
        if (BranchContext::isConstrained()) {
            return Branch::query()->find(BranchContext::id());
        }

        if ($uuid) {
            return Branch::query()->where('uuid', $uuid)->first();
        }

        return null;
    }

    /**
     * @return list<array{uuid: string, name: string}>
     */
    private function branchFilterOptions(?User $user): array
    {
        if (! $user?->isCentralAdmin()) {
            return [];
        }

        return Branch::query()
            ->where('is_active', true)
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn (Branch $branch) => ['uuid' => $branch->uuid, 'name' => $branch->name])
            ->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function fiscalYear(?string $year): array
    {
        $settings = TenantSetting::query()->first();
        $startMonth = (int) ($settings?->fiscal_year_start_month ?: 1);
        $year = (int) ($year ?: now()->year);
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addYear()->subDay()->endOfDay();

        return [$start, $end];
    }
}
