<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Domain\IslamicFinance\Terminology;
use App\Domain\Lending\LoanService;
use App\Domain\Savings\ContributionService;
use App\Domain\Savings\DefaultSavingsCatalog;
use App\Enums\ContributionBucket;
use App\Enums\ContributionFrequency;
use App\Enums\FinanceMode;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Contribution;
use App\Models\ContributionEnrollment;
use App\Models\ContributionProduct;
use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MurabahaFinancing;
use App\Models\QardHasanFinancing;
use App\Support\BranchContext;
use App\Support\FiscalPeriod;
use App\Support\FiscalYearContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class ContributionController extends Controller
{
    public function products()
    {
        return redirect()->route('tenant.products.index', ['tenant' => tenant()?->slug]);
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'finance_mode' => ['required', 'string'],
            'frequency' => ['required', 'string'],
            'minimum_amount' => ['required', 'numeric', 'min:0'],
            'maximum_amount' => ['nullable', 'numeric'],
            'default_amount' => ['nullable', 'numeric'],
            'amount_is_fixed' => ['boolean'],
            'is_locked' => ['boolean'],
            'penalties_enabled' => ['boolean'],
            'penalty_amount' => ['nullable', 'numeric'],
        ]);

        ContributionProduct::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'finance_mode' => FinanceMode::from($data['finance_mode']),
            'frequency' => ContributionFrequency::from($data['frequency']),
            'minimum_amount_kobo' => naira_to_kobo($data['minimum_amount']),
            'maximum_amount_kobo' => isset($data['maximum_amount']) ? naira_to_kobo($data['maximum_amount']) : null,
            'default_amount_kobo' => isset($data['default_amount']) ? naira_to_kobo($data['default_amount']) : null,
            'amount_is_fixed' => $request->boolean('amount_is_fixed'),
            'is_locked' => $request->boolean('is_locked'),
            'penalties_enabled' => $request->boolean('penalties_enabled'),
            'penalty_amount_kobo' => naira_to_kobo($data['penalty_amount'] ?? 0),
            'is_required' => $request->boolean('is_required'),
        ]);

        return back()->with('success', 'Contribution product saved.');
    }

    public function index(Request $request)
    {
        app(DefaultSavingsCatalog::class)->ensure();

        $tab = $request->query('tab') === 'transactions' ? 'transactions' : 'summary';
        $month = $this->month($request->query('month'));
        $branch = $this->selectedBranch($request->query('branch'));
        $needsBranch = $this->needsBranchSelection($branch, $request->user());

        $payload = [
            'tab' => $tab,
            'month' => $month->format('Y-m'),
            'month_label' => $month->format('F Y'),
            'can_filter_branches' => (bool) $request->user()?->isCentralAdmin(),
            'needs_branch' => $needsBranch,
            'branch' => $branch ? ['uuid' => $branch->uuid, 'name' => $branch->name] : null,
            'branches' => $this->branchFilterOptions($request->user()),
            'members' => $needsBranch
                ? []
                : Member::query()->where('status', MemberStatus::Active)
                    ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
                    ->orderBy('first_name')
                    ->get(['id', 'uuid', 'first_name', 'last_name', 'membership_number']),
            'products' => $this->paymentProducts(),
            ...self::paymentCatalog(),
        ];

        if ($tab === 'transactions') {
            $payload['contributions'] = $needsBranch
                ? $this->emptyPage($request)
                : $this->transactionFeed($request, $branch);
            $payload['can_manage_transactions'] = (bool) $request->user()?->can('contributions.manage');
        } else {
            $payload = [...$payload, ...$this->summaryMatrix($month, $branch, $needsBranch)];
        }

        return Inertia::render('Tenant/Savings/Contributions', $payload);
    }

    public function record(Request $request, ContributionService $service, LoanService $loans, IslamicFinancingService $islamic)
    {
        if (! $request->has('payments')) {
            $request->merge([
                'payments' => [[
                    'kind' => $request->input('kind', 'product'),
                    'product_uuid' => $request->input('product_uuid'),
                    'facility_uuid' => $request->input('facility_uuid'),
                    'amount' => $request->input('amount'),
                    'channel' => $request->input('channel', 'cash'),
                    'for_month' => $request->input('for_month'),
                ]],
            ]);
        }

        $data = $request->validate([
            'member_uuid' => ['required', 'uuid'],
            'for_month' => ['nullable', 'date_format:Y-m'],
            'payments' => ['required', 'array', 'min:1', 'max:20'],
            'payments.*.kind' => ['nullable', 'in:product,loan,qard,murabaha'],
            'payments.*.product_uuid' => ['nullable', 'uuid'],
            'payments.*.facility_uuid' => ['nullable', 'uuid'],
            'payments.*.amount' => ['required', 'numeric', 'min:1'],
            'payments.*.channel' => ['nullable', 'string', 'in:cash,transfer,bank_transfer,manual'],
            'paid_on' => ['nullable', 'date'],
        ], [
            'member_uuid.required' => 'Select a member.',
            'payments.required' => 'Add at least one payment.',
            'payments.*.amount.required' => 'Enter an amount.',
            'payments.*.amount.numeric' => 'Enter a valid amount.',
            'payments.*.amount.min' => 'Amount must be at least ₦1.',
            'payments.*.channel.in' => 'Choose Cash or Transfer.',
        ], [
            'member_uuid' => 'member',
            'for_month' => 'month',
            'payments.*.product_uuid' => 'product',
            'payments.*.facility_uuid' => 'loan',
            'payments.*.amount' => 'amount',
            'payments.*.channel' => 'channel',
        ]);

        $member = Member::query()->where('uuid', $data['member_uuid'])->firstOrFail();
        if (! tenant()?->settings?->allowsManualPayments()) {
            return back()->with('error', 'Manual payment posting is turned off. Ask a Super Admin to enable it in Settings.');
        }
        $postedAt = ! empty($data['paid_on'])
            ? Carbon::parse($data['paid_on'])->endOfDay()
            : $this->postedAtForMonth($data['for_month'] ?? null);
        $paidOn = $postedAt->toDateString();

        try {
            DB::transaction(function () use ($data, $member, $service, $loans, $islamic, $postedAt, $paidOn) {
                foreach ($data['payments'] as $index => $line) {
                    $kind = $line['kind'] ?? 'product';
                    $channel = $line['channel'] ?: 'cash';
                    $amountKobo = naira_to_kobo($line['amount']);

                    if ($kind === 'product' || $kind === '' || $kind === null) {
                        $product = ContributionProduct::query()->where('uuid', $line['product_uuid'] ?? null)->first();
                        if (! $product) {
                            throw ValidationException::withMessages([
                                "payments.{$index}.product_uuid" => 'Select a product.',
                            ]);
                        }

                        $service->recordPayment(
                            $member,
                            $product,
                            $amountKobo,
                            $channel,
                            forMonth: $data['for_month'] ?? null,
                            paidOn: $paidOn,
                        );

                        continue;
                    }

                    $facilityUuid = $line['facility_uuid'] ?? null;
                    if (! $facilityUuid) {
                        throw ValidationException::withMessages([
                            "payments.{$index}.facility_uuid" => 'Select a loan.',
                        ]);
                    }

                    if ($kind === 'loan') {
                        $loan = Loan::query()->where('uuid', $facilityUuid)->first();
                        if (! $loan || $loan->member_id !== $member->id) {
                            throw ValidationException::withMessages([
                                "payments.{$index}.facility_uuid" => 'Unknown loan.',
                            ]);
                        }
                        $loans->repay($loan, $amountKobo, $channel, postedAt: $postedAt);

                        continue;
                    }

                    if ($kind === 'qard') {
                        $facility = QardHasanFinancing::query()->where('uuid', $facilityUuid)->first();
                        if (! $facility || $facility->member_id !== $member->id) {
                            throw ValidationException::withMessages([
                                "payments.{$index}.facility_uuid" => 'Unknown Qard Hasan facility.',
                            ]);
                        }
                        $islamic->repayQardHasan($facility, $amountKobo, $channel, postedAt: $postedAt);

                        continue;
                    }

                    $facility = MurabahaFinancing::query()->where('uuid', $facilityUuid)->first();
                    if (! $facility || $facility->member_id !== $member->id) {
                        throw ValidationException::withMessages([
                            "payments.{$index}.facility_uuid" => 'Unknown Murabaha facility.',
                        ]);
                    }
                    $islamic->repayMurabaha($facility, $amountKobo, $channel, postedAt: $postedAt);
                }
            });
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $count = count($data['payments']);

        return back()->with('success', $count === 1 ? 'Payment recorded.' : "{$count} payments recorded.");
    }

    public function update(Request $request, string $tenant, Contribution $contribution, ContributionService $service)
    {
        abort_unless($request->user()?->can('contributions.manage'), 403);

        $data = $request->validate([
            'access_code' => ['required', 'string'],
            'product_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:1'],
            'channel' => ['nullable', 'string', 'in:cash,transfer,bank_transfer'],
            'paid_on' => ['required', 'date'],
            'for_month' => ['nullable', 'date_format:Y-m'],
        ], [
            'access_code.required' => 'Enter the access code.',
            'amount.min' => 'Amount must be at least ₦1.',
        ]);

        $this->assertTransactionAccessCode($data['access_code']);

        $product = ContributionProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail();

        try {
            $service->updatePayment(
                $contribution,
                $product,
                naira_to_kobo($data['amount']),
                $data['channel'] ?: 'cash',
                $data['paid_on'],
                $data['for_month'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaction updated.');
    }

    public function destroy(Request $request, string $tenant, Contribution $contribution, ContributionService $service)
    {
        abort_unless($request->user()?->can('contributions.manage'), 403);

        $data = $request->validate([
            'access_code' => ['required', 'string'],
        ], [
            'access_code.required' => 'Enter the access code.',
        ]);

        $this->assertTransactionAccessCode($data['access_code']);

        try {
            $service->deletePayment($contribution);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaction deleted.');
    }

    private function assertTransactionAccessCode(?string $code): void
    {
        $code = (string) $code;
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

    public function enrollments(Request $request)
    {
        app(DefaultSavingsCatalog::class)->ensure();

        $tab = $request->query('tab') === 'list' ? 'list' : 'view';
        $branch = $this->selectedBranch($request->query('branch'));
        $member = $this->selectedMember($request->query('member'));
        $needsBranch = $this->needsBranchSelection($branch, $request->user());

        $payload = [
            'tab' => $tab,
            'can_filter_branches' => (bool) $request->user()?->isCentralAdmin(),
            'needs_branch' => $needsBranch,
            'branch' => $branch ? ['uuid' => $branch->uuid, 'name' => $branch->name] : null,
            'member' => $member ? [
                'uuid' => $member->uuid,
                'name' => $member->fullName(),
                'membership_number' => $member->membership_number,
            ] : null,
            'branches' => $this->branchFilterOptions($request->user()),
            'members' => $needsBranch
                ? []
                : Member::query()->where('status', MemberStatus::Active)
                    ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
                    ->orderBy('first_name')
                    ->get(['uuid', 'first_name', 'last_name', 'membership_number']),
            'products' => self::paymentProductOptions(),
            'member_plans' => self::paymentCatalog()['member_plans'],
        ];

        if ($tab === 'list') {
            $payload['enrollments'] = $needsBranch
                ? $this->emptyPage($request)
                : Member::query()
                    ->with(['branch', 'enrollments' => fn ($q) => $q->where('status', 'active')->with('product')])
                    ->where('status', MemberStatus::Active)
                    ->whereHas('enrollments', fn ($q) => $q->where('status', 'active'))
                    ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
                    ->when($member, fn ($q) => $q->whereKey($member->id))
                    ->orderBy('membership_number')
                    ->orderBy('last_name')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(function (Member $row) {
                        $plans = $row->enrollments
                            ->sortBy(fn (ContributionEnrollment $e) => ($e->product?->bucket?->sortOrder() ?? 99).($e->product?->name ?? ''))
                            ->values();

                        return [
                            'uuid' => $row->uuid,
                            'member' => $row->fullName(),
                            'membership_number' => $row->membership_number,
                            'branch' => $row->branch?->name,
                            'total' => money($plans->sum('amount_kobo')),
                            'plans' => $plans->map(fn (ContributionEnrollment $e) => [
                                'uuid' => $e->uuid,
                                'product' => $e->product?->name,
                                'amount' => money($e->amount_kobo),
                                'status' => $e->status,
                                'starts_on' => $e->starts_on?->toDateString(),
                            ])->all(),
                        ];
                    });
        } else {
            $payload = [...$payload, ...$this->enrollmentMatrix($branch, $member, $needsBranch)];
        }

        return Inertia::render('Tenant/Savings/Enrollments', $payload);
    }

    public function enroll(Request $request, ContributionService $service)
    {
        if ($request->filled('products') || $request->filled('member_uuids')) {
            $data = $request->validate([
                'member_uuid' => ['nullable', 'uuid', 'required_without:member_uuids'],
                'member_uuids' => ['nullable', 'array', 'min:1', 'required_without:member_uuid'],
                'member_uuids.*' => ['uuid'],
                'products' => ['required', 'array', 'min:1'],
                'products.*.product_uuid' => ['required', 'uuid'],
                'products.*.amount' => ['required', 'numeric', 'min:0'],
            ], [
                'member_uuid.required_without' => 'Select a member.',
                'member_uuids.required_without' => 'Select at least one member.',
                'products.required' => 'Select at least one product.',
            ]);

            $memberUuids = ! empty($data['member_uuids'])
                ? $data['member_uuids']
                : [$data['member_uuid']];

            $members = Member::query()->whereIn('uuid', $memberUuids)->get();
            if ($members->count() !== count(array_unique($memberUuids))) {
                throw ValidationException::withMessages([
                    'member_uuid' => 'One or more members could not be found.',
                ]);
            }

            $products = collect($data['products'])->map(function (array $line, int $index) {
                $product = ContributionProduct::query()->where('uuid', $line['product_uuid'])->first();
                if (! $product) {
                    throw ValidationException::withMessages([
                        "products.{$index}.product_uuid" => 'Unknown savings product.',
                    ]);
                }

                if ($product->is_required && $product->amount_is_fixed) {
                    return null;
                }

                return [$product, naira_to_kobo($line['amount'])];
            })->filter()->values();

            if ($products->isEmpty()) {
                throw ValidationException::withMessages([
                    'products' => 'Select at least one product for the mandate.',
                ]);
            }

            try {
                DB::transaction(function () use ($members, $products, $service) {
                    foreach ($members as $member) {
                        foreach ($products as [$product, $amountKobo]) {
                            $service->enroll($member, $product, $amountKobo);
                        }
                    }
                });
            } catch (InvalidArgumentException $e) {
                return back()->with('error', $e->getMessage());
            }

            $memberCount = $members->count();
            $productCount = $products->count();
            $message = $memberCount === 1 && $productCount === 1
                ? 'Mandate saved.'
                : "Added mandates for {$memberCount} ".($memberCount === 1 ? 'member' : 'members')." on {$productCount} ".($productCount === 1 ? 'product' : 'products').'.';

            return back()->with('success', $message);
        }

        $data = $request->validate([
            'member_uuid' => ['required', 'uuid'],
            'product_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $member = Member::query()->where('uuid', $data['member_uuid'])->firstOrFail();
        $product = ContributionProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail();

        try {
            $service->enroll($member, $product, naira_to_kobo($data['amount']));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'amount' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Mandate saved.');
    }

    public function unenroll(string $tenant, ContributionEnrollment $enrollment)
    {
        abort_unless($enrollment->tenant_id === tenant()?->id, 404);
        $enrollment->delete();

        return back()->with('success', 'Mandate removed.');
    }

    /**
     * @return list<array{uuid: string, name: string, frequency: string|null, default_amount: float|int|null, min_amount: float, max_amount: float|int|null, amount_is_fixed: bool, is_required: bool}>
     */
    public static function paymentProductOptions(): array
    {
        return ContributionProduct::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['uuid', 'name', 'bucket', 'frequency', 'default_amount_kobo', 'minimum_amount_kobo', 'maximum_amount_kobo', 'amount_is_fixed', 'is_required'])
            ->map(fn (ContributionProduct $p) => self::productPaymentPayload($p))
            ->all();
    }

    /**
     * @return array{required_products: list<array<string, mixed>>, member_plans: array<string, list<array<string, mixed>>>, member_paid: array<string, array{one_time: list<string>, yearly: array<string, list<string>>}>, member_facilities: array<string, list<array<string, mixed>>>}
     */
    public static function paymentCatalog(?string $memberUuid = null): array
    {
        $required = ContributionProduct::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->where(function ($q) {
                $q->whereNull('bucket')
                    ->orWhere('bucket', '!=', ContributionBucket::Mds->value);
            })
            ->orderBy('name')
            ->get(['uuid', 'name', 'bucket', 'frequency', 'default_amount_kobo', 'minimum_amount_kobo', 'maximum_amount_kobo', 'amount_is_fixed', 'is_required'])
            ->map(fn (ContributionProduct $p) => [
                ...self::productPaymentPayload($p),
                'is_required' => true,
            ])
            ->values()
            ->all();

        $query = ContributionEnrollment::query()
            ->where('status', 'active')
            ->with(['member:id,uuid', 'product']);

        if ($memberUuid) {
            $query->whereHas('member', fn ($q) => $q->where('uuid', $memberUuid));
        }

        $memberPlans = [];
        foreach ($query->get() as $enrollment) {
            $member = $enrollment->member;
            $product = $enrollment->product;
            if (! $member?->uuid || ! $product?->is_active) {
                continue;
            }
            $memberPlans[$member->uuid][] = [
                ...self::productPaymentPayload($product, $enrollment->amount_kobo / 100),
                'is_required' => (bool) $product->is_required,
                'enrolled' => true,
            ];
        }

        return [
            'required_products' => $required,
            'member_plans' => $memberPlans,
            'member_paid' => self::memberPaidProducts($memberUuid),
            'member_facilities' => self::memberFacilities($memberUuid),
        ];
    }

    /**
     * @return array{uuid: string, name: string, bucket: string|null, frequency: string|null, default_amount: float|int|null, min_amount: float, max_amount: float|int|null, amount_is_fixed: bool, is_required: bool}
     */
    private static function productPaymentPayload(ContributionProduct $product, float|int|null $defaultOverride = null): array
    {
        return [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'bucket' => $product->bucket?->value,
            'frequency' => $product->frequency?->value,
            'default_amount' => $defaultOverride ?? ($product->default_amount_kobo ? $product->default_amount_kobo / 100 : null),
            'min_amount' => $product->minimum_amount_kobo / 100,
            'max_amount' => $product->maximum_amount_kobo ? $product->maximum_amount_kobo / 100 : null,
            'amount_is_fixed' => (bool) $product->amount_is_fixed,
            'is_required' => (bool) $product->is_required,
        ];
    }

    /**
     * @return array<string, array{one_time: list<string>, yearly: array<string, list<string>>}>
     */
    private static function memberPaidProducts(?string $memberUuid = null): array
    {
        $query = Contribution::query()
            ->with(['member:id,uuid', 'product:id,uuid,frequency'])
            ->whereHas('product', fn ($q) => $q->whereIn('frequency', [
                ContributionFrequency::OneTime->value,
                ContributionFrequency::Yearly->value,
            ]));

        if ($memberUuid) {
            $query->whereHas('member', fn ($q) => $q->where('uuid', $memberUuid));
        }

        $paid = [];

        foreach ($query->get(['member_id', 'contribution_product_id', 'for_month', 'paid_on']) as $contribution) {
            $memberKey = $contribution->member?->uuid;
            $productUuid = $contribution->product?->uuid;
            $frequency = $contribution->product?->frequency;

            if (! $memberKey || ! $productUuid || ! $frequency) {
                continue;
            }

            $paid[$memberKey] ??= ['one_time' => [], 'yearly' => []];

            if ($frequency === ContributionFrequency::OneTime) {
                if (! in_array($productUuid, $paid[$memberKey]['one_time'], true)) {
                    $paid[$memberKey]['one_time'][] = $productUuid;
                }

                continue;
            }

            $anchor = $contribution->for_month ?? $contribution->paid_on;
            if (! $anchor) {
                continue;
            }

            $fyYear = (string) FiscalPeriod::forDate($anchor)->startYear();
            $paid[$memberKey]['yearly'][$fyYear] ??= [];
            if (! in_array($productUuid, $paid[$memberKey]['yearly'][$fyYear], true)) {
                $paid[$memberKey]['yearly'][$fyYear][] = $productUuid;
            }
        }

        return $paid;
    }

    /**
     * Outstanding conventional and Islamic facilities that can be collected with monthly payments.
     *
     * @return array<string, list<array{kind: string, uuid: string, name: string, account: string, default_amount: float|int, outstanding: float|int, paid_months: list<string>}>>
     */
    private static function memberFacilities(?string $memberUuid = null): array
    {
        $live = [LoanStatus::Active, LoanStatus::Disbursed, LoanStatus::Defaulted];

        $loans = Loan::query()
            ->with([
                'member:id,uuid',
                'product:id,name',
                'repayments' => fn ($q) => $q->orderBy('installment_number'),
            ])
            ->whereIn('status', $live)
            ->where('outstanding_kobo', '>', 0)
            ->when($memberUuid, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('uuid', $memberUuid)))
            ->get();

        $qard = QardHasanFinancing::query()
            ->with([
                'member:id,uuid',
                'product:id,name',
                'installments' => fn ($q) => $q->orderBy('installment_number'),
            ])
            ->whereIn('status', $live)
            ->where('outstanding_kobo', '>', 0)
            ->when($memberUuid, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('uuid', $memberUuid)))
            ->get();

        $murabaha = MurabahaFinancing::query()
            ->with([
                'member:id,uuid',
                'product:id,name',
                'installments' => fn ($q) => $q->orderBy('installment_number'),
            ])
            ->whereIn('status', $live)
            ->where('outstanding_kobo', '>', 0)
            ->when($memberUuid, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('uuid', $memberUuid)))
            ->get();

        $related = collect()
            ->concat($loans->map(fn (Loan $row) => [$row->getMorphClass(), $row->id]))
            ->concat($qard->map(fn (QardHasanFinancing $row) => [$row->getMorphClass(), $row->id]))
            ->concat($murabaha->map(fn (MurabahaFinancing $row) => [$row->getMorphClass(), $row->id]));

        $paidMonths = [];
        if ($related->isNotEmpty()) {
            $types = $related->pluck(0)->unique()->all();
            $ids = $related->pluck(1)->unique()->all();
            $txns = FinancialTransaction::query()
                ->whereIn('type', ['loan_repayment', 'qard_hasan_repayment', 'murabaha_repayment'])
                ->where('status', TransactionStatus::Successful)
                ->whereIn('related_type', $types)
                ->whereIn('related_id', $ids)
                ->get(['related_type', 'related_id', 'initiated_at']);

            foreach ($txns as $txn) {
                if (! $txn->related_type || ! $txn->related_id || ! $txn->initiated_at) {
                    continue;
                }
                $key = $txn->related_type.':'.$txn->related_id;
                $ym = $txn->initiated_at->format('Y-m');
                $paidMonths[$key][$ym] = true;
            }
        }

        $catalog = [];

        foreach ($loans as $loan) {
            $memberKey = $loan->member?->uuid;
            if (! $memberKey) {
                continue;
            }
            $next = $loan->repayments->first(fn ($row) => $row->status !== 'paid');
            $defaultKobo = $next?->outstandingKobo() ?: (int) $loan->outstanding_kobo;
            $catalog[$memberKey][] = self::facilityPayload(
                'loan',
                $loan->uuid,
                self::repaymentLabel($loan->product?->name, 'Loan repayment'),
                $loan->account_number,
                $defaultKobo,
                (int) $loan->outstanding_kobo,
                array_keys($paidMonths[$loan->getMorphClass().':'.$loan->id] ?? []),
                self::isMdsNamed($loan->product?->name),
            );
        }

        foreach ($qard as $facility) {
            $memberKey = $facility->member?->uuid;
            if (! $memberKey) {
                continue;
            }
            $next = $facility->installments->first(fn ($row) => $row->status !== 'paid');
            $defaultKobo = $next?->outstandingKobo() ?: (int) $facility->outstanding_kobo;
            $catalog[$memberKey][] = self::facilityPayload(
                'qard',
                $facility->uuid,
                self::repaymentLabel($facility->product?->name, 'Qard Hasan repayment'),
                $facility->account_number,
                $defaultKobo,
                (int) $facility->outstanding_kobo,
                array_keys($paidMonths[$facility->getMorphClass().':'.$facility->id] ?? []),
                self::isMdsNamed($facility->product?->name),
            );
        }

        foreach ($murabaha as $facility) {
            $memberKey = $facility->member?->uuid;
            if (! $memberKey) {
                continue;
            }
            $next = $facility->installments->first(fn ($row) => $row->status !== 'paid');
            $defaultKobo = $next?->outstandingKobo() ?: (int) $facility->outstanding_kobo;
            $catalog[$memberKey][] = self::facilityPayload(
                'murabaha',
                $facility->uuid,
                self::repaymentLabel($facility->product?->name, 'Murabaha repayment'),
                $facility->account_number,
                $defaultKobo,
                (int) $facility->outstanding_kobo,
                array_keys($paidMonths[$facility->getMorphClass().':'.$facility->id] ?? []),
                self::isMdsNamed($facility->product?->name),
            );
        }

        return $catalog;
    }

    private static function repaymentLabel(?string $productName, string $fallback): string
    {
        $name = trim((string) $productName);
        if ($name === '') {
            return $fallback;
        }
        if (str_ends_with(mb_strtolower($name), 'repayment')) {
            return $name;
        }

        return $name.' repayment';
    }

    private static function isMdsNamed(?string $name): bool
    {
        $needle = mb_strtolower(trim((string) $name));
        if ($needle === '') {
            return false;
        }

        return $needle === 'mds'
            || preg_match('/\bmds\b/', $needle) === 1
            || str_contains($needle, 'monthly deposit')
            || str_contains($needle, 'monthly deduction');
    }

    /**
     * @param  list<string>  $paidMonths
     * @return array{kind: string, uuid: string, name: string, account: string, default_amount: float|int, outstanding: float|int, paid_months: list<string>, is_mds: bool}
     */
    private static function facilityPayload(
        string $kind,
        string $uuid,
        string $name,
        string $account,
        int $defaultKobo,
        int $outstandingKobo,
        array $paidMonths,
        bool $isMds = false,
    ): array {
        return [
            'kind' => $kind,
            'uuid' => $uuid,
            'name' => $name,
            'account' => $account,
            'default_amount' => $defaultKobo / 100,
            'outstanding' => $outstandingKobo / 100,
            'paid_months' => array_values($paidMonths),
            'is_mds' => $isMds,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function transactionFeed(Request $request, ?Branch $branch): LengthAwarePaginator
    {
        $contributions = Contribution::query()
            ->with(['member.branch', 'product']);
        FiscalYearContext::constrain($contributions, 'paid_on');
        $contributions->when($branch, fn ($q) => $q->where('branch_id', $branch->id));

        $rows = $contributions->get()->map(fn (Contribution $c) => [
            'uuid' => $c->uuid,
            'kind' => 'contribution',
            'editable' => true,
            'member' => $c->member->fullName(),
            'branch' => $c->member?->branch?->name,
            'product' => $c->product?->name ?? 'Contribution',
            'product_uuid' => $c->product?->uuid,
            'amount' => money($c->amount_kobo),
            'amount_naira' => round($c->amount_kobo / 100, 2),
            'paid_on' => $c->paid_on->toDateString(),
            'for_month' => $c->for_month?->format('M Y'),
            'for_month_value' => $c->for_month?->format('Y-m'),
            'channel' => $c->channel === 'bank_transfer' ? 'transfer' : $c->channel,
            'sort' => $c->paid_on->timestamp,
        ]);

        $repayments = FinancialTransaction::query()
            ->with([
                'member.branch',
                'related' => fn (MorphTo $morph) => $morph->morphWith([
                    Loan::class => ['product'],
                    QardHasanFinancing::class => ['product'],
                    MurabahaFinancing::class => ['product'],
                ]),
            ])
            ->whereIn('type', ['loan_repayment', 'qard_hasan_repayment', 'murabaha_repayment'])
            ->where('status', TransactionStatus::Successful);
        FiscalYearContext::constrain($repayments, 'initiated_at');
        $repayments->when($branch, fn ($q) => $q->where('branch_id', $branch->id));

        $repaymentRows = $repayments->get()->map(function (FinancialTransaction $txn) {
            $related = $txn->related;
            $product = $related?->product?->name;
            $paidOn = $txn->initiated_at?->toDateString() ?: $txn->created_at?->toDateString();
            $month = $txn->initiated_at?->copy()->startOfMonth();

            return [
                'uuid' => $txn->uuid,
                'kind' => $txn->type,
                'editable' => false,
                'member' => $txn->member?->fullName() ?: '—',
                'branch' => $txn->member?->branch?->name,
                'product' => self::repaymentLabel($product, 'Loan repayment'),
                'product_uuid' => null,
                'amount' => money($txn->amount_kobo),
                'amount_naira' => round($txn->amount_kobo / 100, 2),
                'paid_on' => $paidOn,
                'for_month' => $month?->format('M Y'),
                'for_month_value' => $month?->format('Y-m'),
                'channel' => $txn->channel === 'bank_transfer' ? 'transfer' : $txn->channel,
                'sort' => $txn->initiated_at?->timestamp ?: $txn->created_at?->timestamp ?: 0,
            ];
        });

        $merged = $rows->concat($repaymentRows)
            ->sortByDesc('sort')
            ->values()
            ->map(function (array $row) {
                unset($row['sort']);

                return $row;
            });

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        return new LengthAwarePaginator(
            $merged->forPage($page, $perPage)->values(),
            $merged->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function summaryMatrix(Carbon $month, ?Branch $branch, bool $skipMembers = false): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $products = ContributionProduct::query()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (ContributionProduct $p) => ($p->bucket?->sortOrder() ?? 99).$p->name)
            ->values();

        $members = $skipMembers
            ? collect()
            : Member::query()
                ->with('sponsor')
                ->where('status', MemberStatus::Active)
                ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
                ->orderBy('membership_number')
                ->orderBy('last_name')
                ->get();

        $posted = Contribution::query()
            ->where(function ($q) use ($start, $end) {
                $q->whereDate('for_month', $start->toDateString())
                    ->orWhere(function ($inner) use ($start, $end) {
                        $inner->whereNull('for_month')
                            ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()]);
                    });
            })
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->selectRaw('member_id, contribution_product_id, sum(amount_kobo) as total_kobo')
            ->groupBy('member_id', 'contribution_product_id')
            ->get()
            ->keyBy(fn ($row) => $row->member_id.':'.$row->contribution_product_id);

        $loanKey = '__loan_repayment__';
        $repayments = FinancialTransaction::query()
            ->whereIn('type', ['loan_repayment', 'qard_hasan_repayment', 'murabaha_repayment'])
            ->where('status', TransactionStatus::Successful)
            ->whereBetween('initiated_at', [$start, $end])
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->selectRaw('member_id, sum(amount_kobo) as total_kobo')
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');

        $repaymentLabel = Terminology::for(tenant_or_fail())['repayment'];
        $columns = $products->map(fn (ContributionProduct $p) => [
            'uuid' => $p->uuid,
            'name' => $p->name,
            'bucket' => $p->bucket?->value ?? 'other',
            'label' => $p->bucket?->label() ?? $p->name,
        ])->all();
        $columns[] = [
            'uuid' => $loanKey,
            'name' => 'Loan repayment',
            'bucket' => 'loan_repayment',
            'label' => $repaymentLabel,
        ];

        $columnTotals = [];
        foreach ($products as $product) {
            $columnTotals[$product->uuid] = 0;
        }
        $columnTotals[$loanKey] = 0;
        $grandTotal = 0;

        $rows = $members->map(function (Member $member) use ($products, $posted, $repayments, $loanKey, &$columnTotals, &$grandTotal) {
            $cells = [];
            $total = 0;
            foreach ($products as $product) {
                $kobo = (int) ($posted[$member->id.':'.$product->id]->total_kobo ?? 0);
                $total += $kobo;
                $columnTotals[$product->uuid] += $kobo;
                $cells[$product->uuid] = $kobo > 0 ? money($kobo) : '—';
            }
            $loanKobo = (int) ($repayments[$member->id]->total_kobo ?? 0);
            $total += $loanKobo;
            $columnTotals[$loanKey] += $loanKobo;
            $cells[$loanKey] = $loanKobo > 0 ? money($loanKobo) : '—';
            $grandTotal += $total;

            return [
                'uuid' => $member->uuid,
                'number' => $member->membership_number,
                'name' => $member->fullName(),
                'introducer' => $member->sponsor?->fullName(),
                'cells' => $cells,
                'total' => money($total),
            ];
        })->all();

        $footerCells = [];
        foreach ($products as $product) {
            $kobo = (int) $columnTotals[$product->uuid];
            $footerCells[$product->uuid] = $kobo > 0 ? money($kobo) : '—';
        }
        $loanTotal = (int) $columnTotals[$loanKey];
        $footerCells[$loanKey] = $loanTotal > 0 ? money($loanTotal) : '—';

        return [
            'columns' => $columns,
            'rows' => $rows,
            'column_totals' => $footerCells,
            'grand_total' => money($grandTotal),
        ];
    }

    /**
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, column_totals: array<string, string>, grand_total: string}
     */
    private function enrollmentMatrix(?Branch $branch, ?Member $member, bool $skipMembers = false): array
    {
        $products = ContributionProduct::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (ContributionProduct $p) => ! ($p->is_required && $p->amount_is_fixed))
            ->sortBy(fn (ContributionProduct $p) => ($p->bucket?->sortOrder() ?? 99).$p->name)
            ->values();

        $members = $skipMembers
            ? collect()
            : Member::query()
                ->with('branch')
                ->where('status', MemberStatus::Active)
                ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
                ->when($member, fn ($q) => $q->whereKey($member->id))
                ->whereHas('enrollments', fn ($q) => $q->where('status', 'active'))
                ->orderBy('membership_number')
                ->orderBy('last_name')
                ->get();

        $posted = $skipMembers
            ? collect()
            : ContributionEnrollment::query()
                ->where('status', 'active')
                ->when($branch, fn ($q) => $q->whereHas('member', fn ($m) => $m->where('branch_id', $branch->id)))
                ->when($member, fn ($q) => $q->where('member_id', $member->id))
                ->get(['id', 'uuid', 'member_id', 'contribution_product_id', 'amount_kobo'])
                ->keyBy(fn ($row) => $row->member_id.':'.$row->contribution_product_id);

        $columns = $products->map(fn (ContributionProduct $p) => [
            'uuid' => $p->uuid,
            'name' => $p->name,
            'label' => $p->bucket?->label() ?? $p->name,
        ])->all();

        $columnTotals = [];
        foreach ($products as $product) {
            $columnTotals[$product->uuid] = 0;
        }
        $grandTotal = 0;

        $rows = $members->map(function (Member $row) use ($products, $posted, &$columnTotals, &$grandTotal) {
            $cells = [];
            $total = 0;
            foreach ($products as $product) {
                $enrollment = $posted[$row->id.':'.$product->id] ?? null;
                $kobo = (int) ($enrollment->amount_kobo ?? 0);
                $total += $kobo;
                $columnTotals[$product->uuid] += $kobo;
                $cells[$product->uuid] = $kobo > 0 ? money($kobo) : '—';
            }
            $grandTotal += $total;

            return [
                'uuid' => $row->uuid,
                'number' => $row->membership_number,
                'name' => $row->fullName(),
                'branch' => $row->branch?->name,
                'cells' => $cells,
                'total' => money($total),
            ];
        })->all();

        $footerCells = [];
        foreach ($products as $product) {
            $kobo = (int) $columnTotals[$product->uuid];
            $footerCells[$product->uuid] = $kobo > 0 ? money($kobo) : '—';
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'column_totals' => $footerCells,
            'grand_total' => money($grandTotal),
        ];
    }

    private function paymentProducts(): array
    {
        return self::paymentProductOptions();
    }

    private function month(?string $value): Carbon
    {
        try {
            return $value
                ? Carbon::createFromFormat('Y-m', $value)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    private function postedAtForMonth(?string $forMonth): Carbon
    {
        if (! $forMonth) {
            return now();
        }

        try {
            $at = Carbon::createFromFormat('Y-m', $forMonth)->endOfMonth();
        } catch (\Throwable) {
            return now();
        }

        return $at->isFuture() ? now() : $at;
    }

    private function needsBranchSelection(?Branch $branch, mixed $user): bool
    {
        return (bool) $user?->isCentralAdmin() && ! $branch;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyPage(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
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

    private function selectedMember(?string $uuid): ?Member
    {
        if (! $uuid) {
            return null;
        }

        return Member::query()->where('uuid', $uuid)->first();
    }

    /**
     * @return list<array{uuid: string, name: string}>
     */
    private function branchFilterOptions(mixed $user): array
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
}
