<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Payments\MonipayClient;
use App\Enums\ContributionBucket;
use App\Enums\ContributionFrequency;
use App\Enums\FinanceMode;
use App\Enums\InterestMethod;
use App\Enums\IslamicStructure;
use App\Enums\MemberStatus;
use App\Enums\ShariahStatus;
use App\Http\Controllers\Controller;
use App\Models\ContributionProduct;
use App\Models\IslamicFinancingProduct;
use App\Models\LoanProduct;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class ProductController extends Controller
{
    public function index()
    {
        $tenant = tenant_or_fail();
        $items = collect()
            ->concat($this->savingsPayload())
            ->concat($tenant->offersConventional() ? $this->loanPayload() : [])
            ->concat($tenant->offersIslamic() ? $this->islamicPayload() : [])
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('Tenant/Products/Index', [
            'products' => $items,
            'counts' => [
                'all' => $items->count(),
                'active' => $items->where('is_active', true)->count(),
                'savings' => $items->where('kind', 'savings')->count(),
                'loan' => $items->where('kind', 'loan')->count(),
                'islamic' => $items->where('kind', 'islamic')->count(),
            ],
            'kinds' => $this->kindOptions($tenant->offersConventional(), $tenant->offersIslamic()),
            'members' => Member::query()->where('status', MemberStatus::Active)->orderBy('first_name')
                ->get(['uuid', 'first_name', 'last_name', 'membership_number']),
            'savings_products' => ContributionController::paymentProductOptions(),
            'buckets' => collect(ContributionBucket::cases())->map(fn (ContributionBucket $b) => [
                'value' => $b->value,
                'label' => $b->label(),
            ]),
            'monipay_configured' => (new MonipayClient)->configured(),
            'revenue_heads' => [],
            'settlement_banks' => [],
            'monipay_error' => null,
        ]);
    }

    public function monipayOptions()
    {
        return response()->json($this->monipayCatalog());
    }

    public function store(Request $request)
    {
        $kind = $this->validatedKind($request);

        if ($kind === 'savings') {
            ContributionProduct::query()->create($this->savingsAttributes($request));
        } elseif ($kind === 'loan') {
            LoanProduct::query()->create($this->loanAttributes($request));
        } else {
            IslamicFinancingProduct::query()->create([
                ...$this->islamicAttributes($request),
                'shariah_status' => ShariahStatus::Draft,
            ]);
        }

        return back()->with('success', 'Product saved.');
    }

    public function update(Request $request, string $tenant, string $kind, string $uuid)
    {
        $kind = $this->assertKind($kind);
        $product = $this->find($kind, $uuid);

        if ($kind === 'savings') {
            $product->update($this->savingsAttributes($request, $product));
        } elseif ($kind === 'loan') {
            $product->update($this->loanAttributes($request, $product));
        } else {
            $product->update($this->islamicAttributes($request, $product));
        }

        return back()->with('success', 'Product updated.');
    }

    public function toggle(string $tenant, string $kind, string $uuid)
    {
        $kind = $this->assertKind($kind);
        $product = $this->find($kind, $uuid);
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->is_active ? 'Product activated.' : 'Product deactivated.');
    }

    public function destroy(string $tenant, string $kind, string $uuid)
    {
        $kind = $this->assertKind($kind);
        $product = $this->find($kind, $uuid);

        if ($product->is_active) {
            throw ValidationException::withMessages([
                'product' => 'Deactivate the product before deleting it.',
            ]);
        }

        if ($this->hasPaymentRecords($kind, $product)) {
            throw ValidationException::withMessages([
                'product' => 'This product has payment records and cannot be deleted.',
            ]);
        }

        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function savingsPayload(): array
    {
        return ContributionProduct::query()
            ->withCount(['contributions', 'withdrawals'])
            ->latest()
            ->get()
            ->map(fn (ContributionProduct $p) => [
            'uuid' => $p->uuid,
            'kind' => 'savings',
            'kind_label' => 'Savings',
            'name' => $p->name,
            'description' => $p->description,
            'summary' => collect([
                $p->bucket?->label(),
                $p->frequency?->label() ?? $p->frequency?->value,
                $p->amount_is_fixed ? 'Fixed amount' : null,
                $p->is_required ? 'Required' : 'Optional',
                $p->monipay_revenue_head_name ?: null,
            ])->filter()->implode(' · '),
            'range' => $this->savingsRange($p),
            'settlement_bank' => $p->monipay_settlement_bank_id ? [
                'name' => $p->monipay_settlement_account_name,
                'bank_name' => $p->monipay_settlement_bank_name,
                'account_no' => $p->monipay_settlement_account_no,
            ] : null,
            'is_active' => $p->is_active,
            'can_delete' => ! $p->is_active && (int) $p->contributions_count === 0 && (int) $p->withdrawals_count === 0,
            'created_at' => $p->created_at?->toDayDateTimeString(),
            'form' => [
                'kind' => 'savings',
                'name' => $p->name,
                'bucket' => $p->bucket?->value ?? 'savings',
                'description' => $p->description ?? '',
                'finance_mode' => $p->finance_mode?->value,
                'frequency' => $p->frequency?->value,
                'minimum_amount' => $this->naira($p->minimum_amount_kobo),
                'maximum_amount' => $this->naira($p->maximum_amount_kobo),
                'default_amount' => $this->naira($p->default_amount_kobo),
                'amount_is_fixed' => $p->amount_is_fixed,
                'is_locked' => $p->is_locked,
                'is_required' => $p->is_required,
                'revenue_head_id' => $p->monipay_revenue_head_id,
                'revenue_head_name' => $p->monipay_revenue_head_name,
                'settlement_bank_id' => $p->monipay_settlement_bank_id,
                'settlement_account_name' => $p->monipay_settlement_account_name,
                'settlement_account_no' => $p->monipay_settlement_account_no,
                'settlement_bank_name' => $p->monipay_settlement_bank_name,
            ],
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loanPayload(): array
    {
        return LoanProduct::query()
            ->withCount(['loans', 'applications'])
            ->latest()
            ->get()
            ->map(fn (LoanProduct $p) => [
            'uuid' => $p->uuid,
            'kind' => 'loan',
            'kind_label' => 'Loan',
            'name' => $p->name,
            'description' => $p->description,
            'summary' => collect([
                str_replace('_', ' ', $p->interest_method?->value ?? ''),
                $p->interest_rate_bps ? (round($p->interest_rate_bps / 100, 2).'% p.a.') : null,
                $p->minimum_tenure_months.'–'.$p->maximum_tenure_months.' months',
            ])->filter()->implode(' · '),
            'range' => $this->range($p->minimum_amount_kobo, $p->maximum_amount_kobo),
            'settlement_bank' => null,
            'is_active' => $p->is_active,
            'can_delete' => ! $p->is_active && (int) $p->loans_count === 0 && (int) $p->applications_count === 0,
            'created_at' => $p->created_at?->toDayDateTimeString(),
            'form' => [
                'kind' => 'loan',
                'name' => $p->name,
                'description' => $p->description ?? '',
                'minimum_amount' => $this->naira($p->minimum_amount_kobo),
                'maximum_amount' => $this->naira($p->maximum_amount_kobo),
                'minimum_tenure_months' => $p->minimum_tenure_months,
                'maximum_tenure_months' => $p->maximum_tenure_months,
                'interest_method' => $p->interest_method?->value,
                'interest_rate' => round($p->interest_rate_bps / 100, 2),
                'processing_fee' => $this->naira($p->processing_fee_kobo),
                'guarantors_required' => $p->guarantors_required,
            ],
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function islamicPayload(): array
    {
        return IslamicFinancingProduct::query()
            ->withCount(['qardHasanFinancings', 'murabahaFinancings'])
            ->latest()
            ->get()
            ->map(fn (IslamicFinancingProduct $p) => [
            'uuid' => $p->uuid,
            'kind' => 'islamic',
            'kind_label' => 'Loan',
            'name' => $p->name,
            'description' => $p->description,
            'summary' => collect([
                $p->structure?->label(),
                $p->shariah_status?->value ? str_replace('_', ' ', $p->shariah_status->value) : null,
                $p->minimum_tenure_months.'–'.$p->maximum_tenure_months.' months',
            ])->filter()->implode(' · '),
            'range' => $this->range($p->minimum_amount_kobo, $p->maximum_amount_kobo),
            'settlement_bank' => $p->monipay_settlement_bank_id ? [
                'name' => $p->monipay_settlement_account_name,
                'bank_name' => $p->monipay_settlement_bank_name,
                'account_no' => $p->monipay_settlement_account_no,
            ] : null,
            'is_active' => $p->is_active,
            'can_delete' => ! $p->is_active
                && (int) $p->qard_hasan_financings_count === 0
                && (int) $p->murabaha_financings_count === 0,
            'created_at' => $p->created_at?->toDayDateTimeString(),
            'form' => [
                'kind' => 'islamic',
                'name' => $p->name,
                'description' => $p->description ?? '',
                'structure' => $p->structure?->value,
                'minimum_amount' => $this->naira($p->minimum_amount_kobo),
                'maximum_amount' => $this->naira($p->maximum_amount_kobo),
                'minimum_tenure_months' => $p->minimum_tenure_months,
                'maximum_tenure_months' => $p->maximum_tenure_months,
                'admin_fee' => $this->naira($p->admin_fee_kobo),
                'default_profit_rate' => $p->default_profit_bps !== null ? round($p->default_profit_bps / 100, 2) : null,
                'revenue_head_id' => $p->monipay_revenue_head_id,
                'revenue_head_name' => $p->monipay_revenue_head_name,
                'settlement_bank_id' => $p->monipay_settlement_bank_id,
                'settlement_account_name' => $p->monipay_settlement_account_name,
                'settlement_account_no' => $p->monipay_settlement_account_no,
                'settlement_bank_name' => $p->monipay_settlement_bank_name,
            ],
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function savingsAttributes(Request $request, ?ContributionProduct $existing = null): array
    {
        $modes = $this->allowedFinanceModes();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'finance_mode' => ['required', Rule::in($modes)],
            'bucket' => ['nullable', Rule::enum(ContributionBucket::class)],
            'frequency' => ['required', Rule::enum(ContributionFrequency::class)],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'maximum_amount' => ['nullable', 'numeric', 'min:0'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'amount_is_fixed' => ['sometimes', 'boolean'],
            'is_locked' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'revenue_head_id' => ['nullable', 'string', 'max:80'],
            'new_revenue_head_name' => ['nullable', 'string', 'max:160'],
            'settlement_bank_id' => ['nullable', 'string', 'max:80'],
        ]);

        $bucket = ContributionBucket::tryFrom($data['bucket'] ?? '') ?? ContributionBucket::Savings;
        $frequency = ContributionFrequency::from($data['frequency']);
        $allowsWithdrawal = $bucket->allowsWithdrawal();
        $oneTime = $frequency === ContributionFrequency::OneTime;
        $fixed = $request->boolean('amount_is_fixed');
        $defaultKobo = isset($data['default_amount']) && $data['default_amount'] !== ''
            ? naira_to_kobo($data['default_amount'])
            : null;
        $minimumKobo = $oneTime ? 0 : naira_to_kobo($data['minimum_amount'] ?? 0);
        $maximumKobo = (! $oneTime && isset($data['maximum_amount']) && $data['maximum_amount'] !== '')
            ? naira_to_kobo($data['maximum_amount'])
            : null;

        if ($fixed && $defaultKobo !== null) {
            $minimumKobo = $defaultKobo;
            $maximumKobo = $defaultKobo;
        }

        return [
            'name' => $data['name'],
            'bucket' => $bucket,
            'description' => $data['description'] ?? null,
            'finance_mode' => FinanceMode::from($data['finance_mode']),
            'frequency' => $frequency,
            'minimum_amount_kobo' => $minimumKobo,
            'maximum_amount_kobo' => $maximumKobo,
            'default_amount_kobo' => $defaultKobo,
            'amount_is_fixed' => $fixed,
            'is_locked' => $allowsWithdrawal ? $request->boolean('is_locked') : true,
            'is_required' => $request->boolean('is_required'),
            'is_active' => $existing?->is_active ?? true,
            ...$this->resolveMonipayAttributes($data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     monipay_revenue_head_id: ?string,
     *     monipay_revenue_head_name: ?string,
     *     monipay_settlement_bank_id: ?string,
     *     monipay_settlement_account_name: ?string,
     *     monipay_settlement_account_no: ?string,
     *     monipay_settlement_bank_name: ?string
     * }
     */
    private function resolveMonipayAttributes(array $data): array
    {
        $revenueHeadId = filled($data['revenue_head_id'] ?? null) ? (string) $data['revenue_head_id'] : null;
        $settlementBankId = filled($data['settlement_bank_id'] ?? null) ? (string) $data['settlement_bank_id'] : null;
        $newName = trim((string) ($data['new_revenue_head_name'] ?? ''));
        $revenueHeadName = null;

        if (! $revenueHeadId && ! $settlementBankId && $newName === '') {
            return [
                'monipay_revenue_head_id' => null,
                'monipay_revenue_head_name' => null,
                'monipay_settlement_bank_id' => null,
                'monipay_settlement_account_name' => null,
                'monipay_settlement_account_no' => null,
                'monipay_settlement_bank_name' => null,
            ];
        }

        $client = new MonipayClient;
        if (! $client->configured()) {
            throw ValidationException::withMessages([
                'revenue_head_id' => 'Add a Monipay secret key in Settings before linking revenue heads.',
            ]);
        }

        try {
            $catalog = $client->catalog();
            $banks = collect($catalog['settlement_banks'])->keyBy('id');
            $heads = collect($catalog['revenue_heads'])->keyBy('id');
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'revenue_head_id' => $e->getMessage(),
            ]);
        }

        $bank = $settlementBankId ? $banks->get($settlementBankId) : null;

        if ($revenueHeadId === '__create__') {
            if ($newName === '') {
                throw ValidationException::withMessages([
                    'new_revenue_head_name' => 'Enter a name for the new revenue head.',
                ]);
            }
            if (! $bank || empty($bank['account_no']) || empty($bank['bank_code'])) {
                throw ValidationException::withMessages([
                    'settlement_bank_id' => 'Select a settlement bank with an account number and bank code.',
                ]);
            }

            try {
                $created = $client->createRevenueHead([
                    'revname' => $newName,
                    'account_no' => $bank['account_no'],
                    'bank_code' => $bank['bank_code'],
                ]);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages([
                    'new_revenue_head_name' => $e->getMessage(),
                ]);
            }

            $revenueHeadId = $created['id'];
            $revenueHeadName = $created['name'];
            if (! $settlementBankId && $created['settlement_bank_id']) {
                $settlementBankId = $created['settlement_bank_id'];
                $bank = $banks->get($settlementBankId) ?: $bank;
            }
        } else {
            $head = $revenueHeadId ? $heads->get($revenueHeadId) : null;
            $revenueHeadName = $head['name'] ?? null;
            if (! $bank && ($head['settlement_bank_id'] ?? null)) {
                $settlementBankId = $head['settlement_bank_id'];
                $bank = $banks->get($settlementBankId);
                if (! $bank && is_array($head['settlement_bank'] ?? null)) {
                    $linked = $head['settlement_bank'];
                    $bank = [
                        'id' => $linked['id'] ?? $settlementBankId,
                        'account_name' => $linked['name'] ?? $linked['account_name'] ?? null,
                        'account_no' => $linked['account_no'] ?? null,
                        'bank_name' => $linked['bank_name'] ?? null,
                    ];
                }
            }
        }

        return [
            'monipay_revenue_head_id' => $revenueHeadId && $revenueHeadId !== '__create__' ? $revenueHeadId : null,
            'monipay_revenue_head_name' => $revenueHeadName ?? null,
            'monipay_settlement_bank_id' => $bank['id'] ?? $settlementBankId,
            'monipay_settlement_account_name' => $bank['account_name'] ?? $bank['name'] ?? null,
            'monipay_settlement_account_no' => $bank['account_no'] ?? null,
            'monipay_settlement_bank_name' => $bank['bank_name'] ?? null,
        ];
    }

    /**
     * @return array{monipay_configured: bool, revenue_heads: list<array<string, mixed>>, settlement_banks: list<array<string, mixed>>, monipay_error: ?string}
     */
    private function monipayCatalog(): array
    {
        $client = new MonipayClient;
        if (! $client->configured()) {
            return [
                'monipay_configured' => false,
                'revenue_heads' => [],
                'settlement_banks' => [],
                'monipay_error' => null,
            ];
        }

        try {
            $catalog = $client->catalog();

            return [
                'monipay_configured' => true,
                'revenue_heads' => collect($catalog['revenue_heads'])->map(fn ($row) => [
                    'value' => $row['id'],
                    'label' => $row['name'],
                    'settlement_bank_id' => $row['settlement_bank_id'],
                ])->all(),
                'settlement_banks' => collect($catalog['settlement_banks'])->map(fn ($row) => [
                    'value' => $row['id'],
                    'label' => $row['label'],
                    'name' => $row['name'],
                    'account_no' => $row['account_no'],
                    'bank_name' => $row['bank_name'],
                    'bank_code' => $row['bank_code'],
                ])->all(),
                'monipay_error' => null,
            ];
        } catch (RuntimeException $e) {
            return [
                'monipay_configured' => true,
                'revenue_heads' => [],
                'settlement_banks' => [],
                'monipay_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loanAttributes(Request $request, ?LoanProduct $existing = null): array
    {
        abort_unless(tenant_or_fail()->offersConventional(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'minimum_amount' => ['required', 'numeric', 'min:0'],
            'maximum_amount' => ['required', 'numeric', 'min:0'],
            'minimum_tenure_months' => ['required', 'integer', 'min:1'],
            'maximum_tenure_months' => ['required', 'integer', 'min:1'],
            'interest_method' => ['required', Rule::enum(InterestMethod::class)],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'processing_fee' => ['nullable', 'numeric', 'min:0'],
            'guarantors_required' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'minimum_amount_kobo' => naira_to_kobo($data['minimum_amount']),
            'maximum_amount_kobo' => naira_to_kobo($data['maximum_amount']),
            'minimum_tenure_months' => $data['minimum_tenure_months'],
            'maximum_tenure_months' => $data['maximum_tenure_months'],
            'interest_method' => InterestMethod::from($data['interest_method']),
            'interest_rate_bps' => (int) round($data['interest_rate'] * 100),
            'processing_fee_kobo' => naira_to_kobo($data['processing_fee'] ?? 0),
            'guarantors_required' => $data['guarantors_required'] ?? 0,
            'repayment_frequency' => $existing?->repayment_frequency ?? ContributionFrequency::Monthly,
            'is_active' => $existing?->is_active ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function islamicAttributes(Request $request, ?IslamicFinancingProduct $existing = null): array
    {
        abort_unless(tenant_or_fail()->offersIslamic(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'structure' => ['required', Rule::enum(IslamicStructure::class)],
            'minimum_amount' => ['required', 'numeric', 'min:0'],
            'maximum_amount' => ['required', 'numeric', 'min:0'],
            'minimum_tenure_months' => ['nullable', 'integer', 'min:1'],
            'maximum_tenure_months' => ['nullable', 'integer', 'min:1'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'default_profit_rate' => ['nullable', 'numeric', 'min:0'],
            'revenue_head_id' => ['nullable', 'string', 'max:80'],
            'new_revenue_head_name' => ['nullable', 'string', 'max:160'],
            'settlement_bank_id' => ['nullable', 'string', 'max:80'],
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'structure' => IslamicStructure::from($data['structure']),
            'minimum_amount_kobo' => naira_to_kobo($data['minimum_amount']),
            'maximum_amount_kobo' => naira_to_kobo($data['maximum_amount']),
            'minimum_tenure_months' => $data['minimum_tenure_months'] ?? $existing?->minimum_tenure_months ?? 1,
            'maximum_tenure_months' => $data['maximum_tenure_months'] ?? $existing?->maximum_tenure_months ?? 24,
            'admin_fee_kobo' => naira_to_kobo($data['admin_fee'] ?? 0),
            'default_profit_bps' => isset($data['default_profit_rate']) && $data['default_profit_rate'] !== ''
                ? (int) round($data['default_profit_rate'] * 100)
                : null,
            'repayment_frequency' => $existing?->repayment_frequency ?? ContributionFrequency::Monthly,
            'is_active' => $existing?->is_active ?? true,
            ...$this->resolveMonipayAttributes($data),
        ];
    }

    private function validatedKind(Request $request): string
    {
        $allowed = collect($this->kindOptions(
            tenant_or_fail()->offersConventional(),
            tenant_or_fail()->offersIslamic(),
        ))->pluck('value')->all();

        return $request->validate([
            'kind' => ['required', Rule::in($allowed)],
        ])['kind'];
    }

    private function assertKind(string $kind): string
    {
        $allowed = collect($this->kindOptions(
            tenant_or_fail()->offersConventional(),
            tenant_or_fail()->offersIslamic(),
        ))->pluck('value')->all();

        abort_unless(in_array($kind, $allowed, true), 404);

        return $kind;
    }

    private function find(string $kind, string $uuid): ContributionProduct|LoanProduct|IslamicFinancingProduct
    {
        $query = match ($kind) {
            'savings' => ContributionProduct::query(),
            'loan' => LoanProduct::query(),
            default => IslamicFinancingProduct::query(),
        };

        return $query->where('uuid', $uuid)->firstOrFail();
    }

    private function hasPaymentRecords(string $kind, ContributionProduct|LoanProduct|IslamicFinancingProduct $product): bool
    {
        return match ($kind) {
            'savings' => $product instanceof ContributionProduct && (
                $product->contributions()->exists() || $product->withdrawals()->exists()
            ),
            'loan' => $product instanceof LoanProduct && (
                $product->loans()->exists() || $product->applications()->exists()
            ),
            default => $product instanceof IslamicFinancingProduct && (
                $product->qardHasanFinancings()->exists() || $product->murabahaFinancings()->exists()
            ),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function kindOptions(bool $loans, bool $islamic): array
    {
        $kinds = [['value' => 'savings', 'label' => 'Savings']];
        if ($loans) {
            $kinds[] = ['value' => 'loan', 'label' => 'Loan'];
        }
        if ($islamic) {
            $kinds[] = ['value' => 'islamic', 'label' => 'Loan'];
        }

        return $kinds;
    }

    /**
     * @return list<string>
     */
    private function allowedFinanceModes(): array
    {
        $modes = [];
        if (tenant_or_fail()->offersConventional()) {
            $modes[] = FinanceMode::Conventional->value;
        }
        if (tenant_or_fail()->offersIslamic()) {
            $modes[] = FinanceMode::Islamic->value;
        }

        return $modes ?: [FinanceMode::Conventional->value];
    }

    private function savingsRange(ContributionProduct $product): string
    {
        if ($product->amount_is_fixed) {
            $fixed = (int) ($product->default_amount_kobo ?? $product->minimum_amount_kobo ?? 0);

            return money($fixed);
        }

        return $this->range($product->minimum_amount_kobo, $product->maximum_amount_kobo);
    }

    private function range(?int $min, ?int $max): string
    {
        $from = money((int) ($min ?? 0));
        if ($max === null) {
            return "From {$from}";
        }

        return "{$from} – ".money($max);
    }

    private function naira(?int $kobo): ?float
    {
        return $kobo === null ? null : round($kobo / 100, 2);
    }
}
