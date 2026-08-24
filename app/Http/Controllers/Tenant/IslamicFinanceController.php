<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Enums\IslamicStructure;
use App\Enums\ShariahStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Models\IslamicFinancingProduct;
use App\Models\Member;
use App\Models\MurabahaFinancing;
use App\Models\QardHasanFinancing;
use App\Support\FiscalYearContext;
use App\Support\LoanDomainError;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;
use LogicException;

class IslamicFinanceController extends Controller
{
    public function index()
    {
        $qardQuery = QardHasanFinancing::query()->with([
            'member',
            'product',
            'installments' => fn ($q) => $q->orderBy('installment_number'),
        ]);
        FiscalYearContext::constrainDateOrCreated($qardQuery, 'disbursed_on');
        $qard = $qardQuery->latest()->limit(50)->get();

        $murabahaQuery = MurabahaFinancing::query()->with([
            'member',
            'product',
            'installments' => fn ($q) => $q->orderBy('installment_number'),
        ]);
        FiscalYearContext::constrainDateOrCreated($murabahaQuery, 'disbursed_on');
        $murabaha = $murabahaQuery->latest()->limit(50)->get();

        $products = IslamicFinancingProduct::query()->with('shariahApprovals')->latest()->get();
        $ledger = $this->repaymentTransactions($qard->concat($murabaha));

        $qardOutstandingQuery = QardHasanFinancing::query()->whereIn('status', ['active', 'disbursed']);
        FiscalYearContext::constrainDateOrCreated($qardOutstandingQuery, 'disbursed_on');
        $qardOutstanding = (int) $qardOutstandingQuery->sum('outstanding_kobo');

        $murabahaOutstandingQuery = MurabahaFinancing::query()->whereIn('status', ['active', 'disbursed']);
        FiscalYearContext::constrainDateOrCreated($murabahaOutstandingQuery, 'disbursed_on');
        $murabahaOutstanding = (int) $murabahaOutstandingQuery->sum('outstanding_kobo');

        return Inertia::render('Tenant/Islamic/Index', [
            'members' => Member::query()->where('status', 'active')->orderBy('first_name')->get(['uuid', 'first_name', 'last_name', 'membership_number']),
            'kpis' => [
                'qard_outstanding' => money($qardOutstanding),
                'murabaha_outstanding' => money($murabahaOutstanding),
                'facilities' => $qard->count() + $murabaha->count(),
                'products' => $products->count(),
                'pending_shariah' => $products->filter(fn ($p) => in_array($p->shariah_status, [
                    ShariahStatus::Draft,
                    ShariahStatus::UnderReview,
                    ShariahStatus::RequiresChanges,
                ], true))->count(),
            ],
            'products' => $products->map(fn ($p) => [
                'uuid' => $p->uuid,
                'name' => $p->name,
                'structure' => $p->structure->value,
                'structure_label' => $p->structure->label(),
                'minimum' => money($p->minimum_amount_kobo),
                'maximum' => $p->maximum_amount_kobo ? money((int) $p->maximum_amount_kobo) : null,
                'min_tenure' => $p->minimum_tenure_months,
                'max_tenure' => $p->maximum_tenure_months,
                'shariah_status' => $p->shariah_status->value,
            ]),
            'qard' => $qard->map(fn ($f) => [
                'uuid' => $f->uuid,
                'kind' => 'qard_hasan',
                'account' => $f->account_number,
                'member' => $f->member->fullName(),
                'product' => $f->product?->name,
                'purpose' => $f->purpose,
                'principal' => money($f->principal_kobo),
                'admin_fee' => money((int) $f->admin_fee_kobo),
                'total' => money($f->total_repayable_kobo),
                'outstanding' => money($f->outstanding_kobo),
                'next_amount_naira' => $this->nextInstallmentNaira($f),
                'tenure_months' => $f->tenure_months,
                'status' => $f->status->value,
                ...$this->facilitySummary($f),
                ...$this->facilityRepayments($f, $ledger, $f->product?->name ?: 'Qard Hasan'),
            ]),
            'murabaha' => $murabaha->map(fn ($f) => [
                'uuid' => $f->uuid,
                'kind' => 'murabaha',
                'account' => $f->account_number,
                'member' => $f->member->fullName(),
                'product' => $f->product?->name,
                'asset' => $f->asset_description,
                'cost' => money($f->purchase_cost_kobo),
                'profit' => money($f->profit_kobo),
                'down_payment' => money((int) $f->down_payment_kobo),
                'sale_price' => money($f->sale_price_kobo),
                'outstanding' => money($f->outstanding_kobo),
                'next_amount_naira' => $this->nextInstallmentNaira($f),
                'tenure_months' => $f->tenure_months,
                'status' => $f->status->value,
                ...$this->facilitySummary($f),
                ...$this->facilityRepayments($f, $ledger, $f->product?->name ?: 'Murabaha'),
            ]),
        ]);
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'structure' => ['required', 'string'],
            'minimum_amount' => ['required', 'numeric'],
            'maximum_amount' => ['required', 'numeric'],
            'admin_fee' => ['nullable', 'numeric'],
            'default_profit_rate' => ['nullable', 'numeric'],
        ]);

        IslamicFinancingProduct::query()->create([
            'name' => $data['name'],
            'structure' => IslamicStructure::from($data['structure']),
            'minimum_amount_kobo' => naira_to_kobo($data['minimum_amount']),
            'maximum_amount_kobo' => naira_to_kobo($data['maximum_amount']),
            'admin_fee_kobo' => naira_to_kobo($data['admin_fee'] ?? 0),
            'default_profit_bps' => isset($data['default_profit_rate']) ? (int) round($data['default_profit_rate'] * 100) : null,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 24,
            'shariah_status' => ShariahStatus::Draft,
        ]);

        return back()->with('success', 'Loan product created.');
    }

    public function storeQard(Request $request, IslamicFinancingService $service)
    {
        $data = $request->validate([
            'member_uuid' => ['required'],
            'product_uuid' => ['required'],
            'amount' => ['required', 'numeric'],
            'tenure_months' => ['required', 'integer'],
            'purpose' => ['nullable', 'string'],
        ]);

        try {
            $service->createQardHasan(
                Member::query()->where('uuid', $data['member_uuid'])->firstOrFail(),
                IslamicFinancingProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail(),
                naira_to_kobo($data['amount']),
                $data['tenure_months'],
                $data['purpose'] ?? '',
            );
        } catch (InvalidArgumentException|LogicException $e) {
            throw ValidationException::withMessages([
                LoanDomainError::field($e->getMessage()) => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Qard Hasan application submitted.');
    }

    public function disburseQard(string $tenant, QardHasanFinancing $financing, IslamicFinancingService $service)
    {
        try {
            $service->disburseQardHasan($financing);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Qard Hasan disbursed. No interest was applied.');
    }

    public function repayQard(Request $request, string $tenant, QardHasanFinancing $financing, IslamicFinancingService $service)
    {
        $payload = $this->repayPayload($request);

        try {
            $service->repayQardHasan($financing, $payload['amount'], $payload['channel'], postedAt: $payload['posted_at']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Qard Hasan repayment recorded.');
    }

    public function storeMurabaha(Request $request, IslamicFinancingService $service)
    {
        $data = $request->validate([
            'member_uuid' => ['required'],
            'product_uuid' => ['required'],
            'asset_description' => ['required', 'string'],
            'supplier' => ['nullable', 'string'],
            'purchase_cost' => ['required', 'numeric'],
            'profit' => ['required', 'numeric', 'min:0'],
            'down_payment' => ['nullable', 'numeric'],
            'tenure_months' => ['required', 'integer'],
        ]);

        try {
            $service->createMurabaha(
                Member::query()->where('uuid', $data['member_uuid'])->firstOrFail(),
                IslamicFinancingProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail(),
                [
                    'asset_description' => $data['asset_description'],
                    'supplier' => $data['supplier'] ?? null,
                    'purchase_cost_kobo' => naira_to_kobo($data['purchase_cost']),
                    'profit_kobo' => naira_to_kobo($data['profit']),
                    'down_payment_kobo' => naira_to_kobo($data['down_payment'] ?? 0),
                    'tenure_months' => $data['tenure_months'],
                ],
            );
        } catch (InvalidArgumentException|LogicException $e) {
            throw ValidationException::withMessages([
                LoanDomainError::field($e->getMessage(), 'purchase_cost') => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Murabaha request submitted. Sale price = cost + disclosed profit.');
    }

    public function finalizeMurabaha(string $tenant, MurabahaFinancing $financing, IslamicFinancingService $service)
    {
        try {
            $service->finalizeMurabahaSale($financing);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Murabaha sale finalized and frozen.');
    }

    public function repayMurabaha(Request $request, string $tenant, MurabahaFinancing $financing, IslamicFinancingService $service)
    {
        $payload = $this->repayPayload($request);

        try {
            $service->repayMurabaha($financing, $payload['amount'], $payload['channel'], postedAt: $payload['posted_at']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Murabaha installment recorded.');
    }

    public function storeShariahApproval(Request $request)
    {
        $data = $request->validate([
            'product_uuid' => ['required'],
            'adviser_name' => ['required', 'string'],
            'status' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $product = IslamicFinancingProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail();
        $product->shariahApprovals()->create([
            'adviser_name' => $data['adviser_name'],
            'status' => $data['status'],
            'approved_on' => $data['status'] === 'approved' ? now()->toDateString() : null,
            'notes' => $data['notes'] ?? null,
        ]);
        $product->update(['shariah_status' => $data['status']]);

        return back()->with('success', 'Shariah review recorded.');
    }

    /**
     * @return array{amount: int, channel: string, posted_at: Carbon}
     */
    private function repayPayload(Request $request): array
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'channel' => ['nullable', 'string', 'in:cash,transfer,bank_transfer,manual'],
            'paid_on' => ['nullable', 'date'],
        ]);

        return [
            'amount' => naira_to_kobo($data['amount']),
            'channel' => $data['channel'] ?: 'cash',
            'posted_at' => ! empty($data['paid_on']) ? Carbon::parse($data['paid_on'])->endOfDay() : now(),
        ];
    }

    private function nextInstallmentNaira(QardHasanFinancing|MurabahaFinancing $facility): float|int
    {
        $next = $facility->installments->first(fn ($row) => $row->status !== 'paid');

        return ($next?->outstandingKobo() ?: (int) $facility->outstanding_kobo) / 100;
    }

    /**
     * @return array{disbursed_on: ?string, maturity_on: ?string, next_due: ?string, next_amount: ?string, installments_due: int, installments_paid: int}
     */
    private function facilitySummary(QardHasanFinancing|MurabahaFinancing $facility): array
    {
        $installments = $facility->installments->sortBy('installment_number')->values();
        $next = $installments->first(fn ($row) => $row->status !== 'paid');
        $last = $installments->last();
        $maturity = $facility instanceof QardHasanFinancing
            ? $facility->maturity_on?->toDateString()
            : null;

        return [
            'disbursed_on' => $facility->disbursed_on?->toDateString(),
            'maturity_on' => $maturity ?: $last?->due_on?->toDateString(),
            'next_due' => $next?->due_on?->toDateString(),
            'next_amount' => $next ? money($next->outstandingKobo()) : null,
            'installments_due' => $installments->count(),
            'installments_paid' => $installments->where('status', 'paid')->count(),
        ];
    }

    /**
     * @param  Collection<int, QardHasanFinancing|MurabahaFinancing>  $facilities
     * @return Collection<string, Collection<int, FinancialTransaction>>
     */
    private function repaymentTransactions($facilities)
    {
        if ($facilities->isEmpty()) {
            return collect();
        }

        return FinancialTransaction::query()
            ->whereIn('type', ['qard_hasan_repayment', 'murabaha_repayment'])
            ->where('status', TransactionStatus::Successful)
            ->whereIn('related_type', $facilities->map(fn ($f) => $f->getMorphClass())->unique()->all())
            ->whereIn('related_id', $facilities->map(fn ($f) => $f->id)->unique()->all())
            ->orderByDesc('initiated_at')
            ->get()
            ->groupBy(fn (FinancialTransaction $txn) => $txn->related_type.':'.$txn->related_id);
    }

    /**
     * @param  Collection<string, Collection<int, FinancialTransaction>>  $grouped
     * @return array{amount_paid: string, transaction_count: int, repayments: list<array<string, mixed>>}
     */
    private function facilityRepayments(QardHasanFinancing|MurabahaFinancing $facility, $grouped, string $productName): array
    {
        $txns = $grouped->get($facility->getMorphClass().':'.$facility->id, collect());
        $paidKobo = (int) $facility->installments->sum('amount_paid_kobo');

        return [
            'amount_paid' => money($paidKobo),
            'transaction_count' => $txns->count(),
            'repayments' => $txns->map(fn (FinancialTransaction $txn) => [
                'uuid' => $txn->uuid,
                'product' => $productName,
                'amount' => money((int) $txn->amount_kobo),
                'channel' => $this->channelLabel($txn->channel),
                'paid_on' => $txn->initiated_at?->toDateString() ?: $txn->created_at?->toDateString(),
            ])->values()->all(),
        ];
    }

    private function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'bank_transfer', 'transfer' => 'Transfer',
            'cash' => 'Cash',
            'manual' => 'Manual',
            default => $channel ? ucfirst($channel) : '—',
        };
    }
}
