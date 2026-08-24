<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Ledger\WalletService;
use App\Http\Controllers\Controller;
use App\Models\IslamicFinancingProduct;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\MurabahaFinancing;
use App\Models\QardHasanFinancing;
use Inertia\Inertia;

class LoanController extends Controller
{
    public function index(WalletService $wallet)
    {
        $member = $this->member();

        return Inertia::render('Portal/Loans', [
            'wallet' => money($wallet->balance($member)),
            'applications' => $member->loanApplications()
                ->with('product')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($a) => [
                    'uuid' => $a->uuid,
                    'product' => $a->product?->name ?? 'Loan',
                    'amount' => money($a->amount_kobo),
                    'tenure' => $a->tenure_months,
                    'status' => $a->status->value,
                    'submitted_at' => $a->submitted_at?->toFormattedDateString(),
                ]),
            'loans' => $member->loans()
                ->with('product')
                ->latest()
                ->get()
                ->map(fn (Loan $loan) => $this->loanCard($loan)),
            'islamic' => $member->qardHasan()
                ->with('product')
                ->latest()
                ->get()
                ->map(fn (QardHasanFinancing $f) => [
                    'uuid' => $f->uuid,
                    'kind' => 'qard',
                    'product' => $f->product?->name ?? 'Qard Hasan',
                    'account_number' => $f->account_number,
                    'outstanding' => money($f->outstanding_kobo),
                    'status' => $f->status->value,
                ])
                ->concat(
                    $member->murabaha()->with('product')->latest()->get()->map(fn (MurabahaFinancing $f) => [
                        'uuid' => $f->uuid,
                        'kind' => 'murabaha',
                        'product' => $f->product?->name ?? 'Murabaha',
                        'account_number' => $f->account_number,
                        'outstanding' => money($f->outstanding_kobo),
                        'status' => $f->status->value,
                    ])
                )
                ->values(),
            ...$this->financeOptions(),
        ]);
    }

    /**
     * @return array{loan_products: mixed, islamic_products: mixed}
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loanCard(Loan $loan): array
    {
        return [
            'uuid' => $loan->uuid,
            'product' => $loan->product?->name ?? 'Loan',
            'account_number' => $loan->account_number,
            'principal' => money($loan->principal_kobo),
            'outstanding' => money($loan->outstanding_kobo),
            'status' => $loan->status->value,
        ];
    }

    private function member(): Member
    {
        return request()->user()->member()->firstOrFail();
    }
}
