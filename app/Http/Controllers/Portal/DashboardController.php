<?php

namespace App\Http\Controllers\Portal;

use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ledger\WalletService;
use App\Domain\Lending\LoanEligibilityService;
use App\Domain\Lending\LoanService;
use App\Domain\Reporting\StatementService;
use App\Domain\Savings\ContributionService;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ContributionProduct;
use App\Models\IslamicFinancingProduct;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Support\LoanDomainError;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;
use LogicException;

class DashboardController extends Controller
{
    public function __invoke(LedgerService $ledger, WalletService $wallet)
    {
        $member = $this->member();
        $virtual = $member->virtualAccounts()->first();
        $contributions = $member->contributions()
            ->with('product')
            ->latest('paid_on')
            ->latest('id')
            ->paginate(8)
            ->withQueryString()
            ->through(fn ($c) => [
                'uuid' => $c->uuid,
                'product' => $c->product?->name ?? 'Contribution',
                'amount' => money($c->amount_kobo),
                'paid_on' => $c->paid_on?->toFormattedDateString(),
                'channel' => match ($c->channel) {
                    'member_portal' => 'Portal',
                    'transfer' => 'Transfer',
                    'bank_transfer' => 'Bank transfer',
                    'paystack', 'card' => 'Card',
                    'ussd' => 'USSD',
                    default => $c->channel ? str_replace('_', ' ', $c->channel) : 'Recorded',
                },
            ]);

        return Inertia::render('Portal/Dashboard', [
            'member' => [
                'name' => $this->displayName($member->fullName()),
                'first_name' => $this->displayName($member->first_name ?: $member->fullName()),
                'membership_number' => $member->membership_number,
                'status' => $member->status->value,
            ],
            'virtual_account' => $virtual ? [
                'account_name' => $virtual->account_name,
                'account_number' => $virtual->account_number,
                'bank_name' => $virtual->bank_name,
            ] : null,
            'mandates' => $this->portalMandates($member),
            'wallet' => money($wallet->balance($member)),
            'wallet_naira' => round($wallet->balance($member) / 100, 2),
            'savings' => money($ledger->memberSavingsBalance($member)),
            'shares' => money($ledger->memberSharesBalance($member)),
            'loan_balance' => money((int) $member->loans()->where('status', 'active')->sum('outstanding_kobo')),
            'islamic_balance' => money(
                (int) $member->qardHasan()->where('status', 'active')->sum('outstanding_kobo')
                + (int) $member->murabaha()->where('status', 'active')->sum('outstanding_kobo')
            ),
            'announcements' => Announcement::query()
                ->forPortal($member)
                ->limit(1)
                ->get()
                ->map(fn (Announcement $a) => AnnouncementController::card($a)),
            'announcements_total' => Announcement::query()->forPortal($member)->count(),
            'recent' => $contributions,
            ...$this->financeOptions(),
        ]);
    }

    public function contribute(Request $request, ContributionService $service, WalletService $wallet)
    {
        if (! $request->has('payments')) {
            $request->merge([
                'payments' => [[
                    'product_uuid' => $request->input('product_uuid'),
                    'amount' => $request->input('amount'),
                    'channel' => $request->input('channel', 'member_portal'),
                ]],
            ]);
        }

        $data = $request->validate([
            'for_month' => ['nullable', 'date_format:Y-m'],
            'paid_on' => ['nullable', 'date'],
            'payments' => ['required', 'array', 'min:1', 'max:20'],
            'payments.*.product_uuid' => ['required', 'uuid', 'distinct'],
            'payments.*.amount' => ['required', 'numeric', 'min:1'],
            'payments.*.channel' => ['nullable', 'string', 'in:transfer,member_portal,bank_transfer'],
        ], [
            'payments.required' => 'Add at least one contribution.',
            'payments.*.product_uuid.required' => 'Select a product.',
            'payments.*.product_uuid.distinct' => 'Each product can only appear once.',
            'payments.*.amount.required' => 'Enter an amount.',
            'payments.*.amount.numeric' => 'Enter a valid amount.',
            'payments.*.amount.min' => 'Amount must be at least ₦1.',
        ], [
            'for_month' => 'month',
            'payments.*.product_uuid' => 'product',
            'payments.*.amount' => 'amount',
            'payments.*.channel' => 'channel',
        ]);

        $member = $this->member();
        $paidOn = now()->toDateString();
        $forMonth = now()->format('Y-m');
        $totalKobo = collect($data['payments'])->sum(fn ($line) => naira_to_kobo($line['amount']));

        try {
            $wallet->assertSufficient($member, $totalKobo);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['payments' => $e->getMessage()]);
        }

        DB::transaction(function () use ($data, $member, $service, $paidOn, $forMonth) {
            foreach ($data['payments'] as $index => $line) {
                $product = ContributionProduct::query()
                    ->where('uuid', $line['product_uuid'])
                    ->where('is_active', true)
                    ->first();
                if (! $product) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.product_uuid" => 'Select a product.',
                    ]);
                }

                try {
                    $service->recordPayment(
                        $member,
                        $product,
                        naira_to_kobo($line['amount']),
                        'member_portal',
                        forMonth: $forMonth,
                        paidOn: $paidOn,
                        fromWallet: true,
                    );
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.amount" => $e->getMessage(),
                    ]);
                }
            }
        });

        $count = count($data['payments']);

        return back()->with('success', $count === 1 ? 'Contribution received.' : "{$count} contributions received.");
    }

    public function applyLoan(Request $request, LoanService $loans)
    {
        $data = $request->validate([
            'product_uuid' => ['required'],
            'amount' => ['required', 'numeric'],
            'tenure_months' => ['required', 'integer'],
            'purpose' => ['nullable', 'string'],
        ]);

        try {
            $loans->apply(
                $this->member(),
                LoanProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail(),
                naira_to_kobo($data['amount']),
                $data['tenure_months'],
                $data['purpose'] ?? '',
            );
        } catch (InvalidArgumentException $e) {
            $this->throwDomainValidation($e);
        }

        return back()->with('success', 'Your loan application has been submitted.');
    }

    public function applyQard(Request $request, IslamicFinancingService $service)
    {
        $data = $request->validate([
            'product_uuid' => ['required'],
            'amount' => ['required', 'numeric'],
            'tenure_months' => ['required', 'integer'],
            'purpose' => ['nullable', 'string'],
        ]);

        try {
            $service->createQardHasan(
                $this->member(),
                IslamicFinancingProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail(),
                naira_to_kobo($data['amount']),
                $data['tenure_months'],
                $data['purpose'] ?? '',
            );
        } catch (InvalidArgumentException|LogicException $e) {
            $this->throwDomainValidation($e);
        }

        return back()->with('success', 'Your Qard Hasan request has been submitted.');
    }

    public function statement(Request $request, StatementService $statements)
    {
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return Inertia::render('Portal/Statement', [
            'statement' => $statements->memberStatement($this->member(), $from, $to),
            'products' => ContributionProduct::query()->where('is_active', true)->get(['uuid', 'name']),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function applyFinance()
    {
        return redirect()->route('portal.loans', ['tenant' => tenant()?->slug]);
    }

    public function eligibility(Request $request, LoanEligibilityService $eligibility)
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($eligibility->evaluate($this->member(), naira_to_kobo($data['amount'] ?? 0)));
    }

    /**
     * @return array{loan_products: mixed, islamic_products: mixed, savings_products: mixed}
     */
    private function financeOptions(): array
    {
        return [
            'loan_products' => tenant()->offersConventional()
                ? LoanProduct::query()->where('is_active', true)->get(['uuid', 'name', 'minimum_amount_kobo', 'maximum_amount_kobo', 'minimum_tenure_months', 'maximum_tenure_months'])
                    ->map(fn (LoanProduct $p) => [
                        'uuid' => $p->uuid,
                        'name' => $p->name,
                        'minimum' => money($p->minimum_amount_kobo),
                        'maximum' => $p->maximum_amount_kobo ? money($p->maximum_amount_kobo) : null,
                        'min_naira' => round($p->minimum_amount_kobo / 100, 2),
                        'max_naira' => $p->maximum_amount_kobo ? round($p->maximum_amount_kobo / 100, 2) : null,
                        'min_tenure' => $p->minimum_tenure_months,
                        'max_tenure' => $p->maximum_tenure_months,
                    ])
                : [],
            'islamic_products' => tenant()->offersIslamic()
                ? IslamicFinancingProduct::query()->where('is_active', true)->get([
                    'uuid', 'name', 'structure', 'minimum_amount_kobo', 'maximum_amount_kobo',
                    'minimum_tenure_months', 'maximum_tenure_months',
                ])
                    ->map(fn (IslamicFinancingProduct $p) => [
                        'uuid' => $p->uuid,
                        'name' => $p->name,
                        'structure' => $p->structure->value,
                        'minimum' => money((int) $p->minimum_amount_kobo),
                        'maximum' => $p->maximum_amount_kobo ? money((int) $p->maximum_amount_kobo) : null,
                        'min_naira' => round($p->minimum_amount_kobo / 100, 2),
                        'max_naira' => $p->maximum_amount_kobo ? round($p->maximum_amount_kobo / 100, 2) : null,
                        'min_tenure' => $p->minimum_tenure_months,
                        'max_tenure' => $p->maximum_tenure_months,
                    ])
                : [],
            'savings_products' => ContributionProduct::query()->where('is_active', true)->get([
                'uuid', 'name', 'default_amount_kobo', 'minimum_amount_kobo', 'maximum_amount_kobo',
                'frequency', 'amount_is_fixed', 'is_required',
            ])
                ->map(fn (ContributionProduct $p) => [
                    'uuid' => $p->uuid,
                    'name' => $p->name,
                    'default_amount' => $p->default_amount_kobo ? round($p->default_amount_kobo / 100, 2) : null,
                    'min_amount' => round($p->minimum_amount_kobo / 100, 2),
                    'max_amount' => $p->maximum_amount_kobo ? round($p->maximum_amount_kobo / 100, 2) : null,
                    'amount_is_fixed' => (bool) $p->amount_is_fixed,
                    'is_required' => (bool) $p->is_required,
                ]),
        ];
    }

    /**
     * @return Collection<int, array{uuid: string, product: string, product_uuid: string|null, amount: string, amount_naira: float|null, amount_is_fixed: bool, is_required: bool, starts_on: string|null}>
     */
    private function portalMandates(Member $member)
    {
        return $member->enrollments()
            ->with('product')
            ->where('status', 'active')
            ->get()
            ->filter(fn ($e) => $e->product && ! $e->product->is_required)
            ->values()
            ->map(fn ($e) => [
                'uuid' => $e->uuid,
                'product' => $e->product?->name ?? 'Savings plan',
                'product_uuid' => $e->product?->uuid,
                'amount' => money($e->amount_kobo),
                'amount_naira' => $e->amount_kobo ? round($e->amount_kobo / 100, 2) : null,
                'amount_is_fixed' => (bool) $e->product?->amount_is_fixed,
                'is_required' => (bool) $e->product?->is_required,
                'starts_on' => $e->starts_on?->toFormattedDateString(),
            ]);
    }

    private function displayName(string $name): string
    {
        return mb_convert_case(mb_strtolower(trim($name)), MB_CASE_TITLE, 'UTF-8');
    }

    private function member(): Member
    {
        return request()->user()->member()->firstOrFail();
    }

    private function throwDomainValidation(\Throwable $e): never
    {
        $message = $e->getMessage();
        $field = LoanDomainError::field($message, match (true) {
            str_contains($message, 'structure'), str_contains($message, 'active members') => 'product_uuid',
            default => 'amount',
        });

        throw ValidationException::withMessages([$field => $message]);
    }
}
