<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Ledger\WalletService;
use App\Http\Controllers\Controller;
use App\Models\FinancialTransaction;
use App\Models\Member;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function __invoke(WalletService $wallet)
    {
        $member = $this->member();
        $virtual = $member->virtualAccounts()->first();
        $balance = $wallet->balance($member);

        return Inertia::render('Portal/Wallet', [
            'wallet' => money($balance),
            'virtual_account' => $virtual ? [
                'account_name' => $virtual->account_name,
                'account_number' => $virtual->account_number,
                'bank_name' => $virtual->bank_name,
            ] : null,
            'activity' => FinancialTransaction::query()
                ->where('member_id', $member->id)
                ->whereIn('type', ['wallet_credit', 'contribution', 'loan_repayment', 'qard_hasan_repayment', 'murabaha_repayment'])
                ->latest('initiated_at')
                ->limit(12)
                ->get()
                ->map(fn (FinancialTransaction $txn) => [
                    'uuid' => $txn->uuid,
                    'type' => match ($txn->type) {
                        'wallet_credit' => 'Wallet credit',
                        'contribution' => 'Contribution',
                        'loan_repayment' => 'Loan repayment',
                        'qard_hasan_repayment' => 'Qard Hasan repayment',
                        'murabaha_repayment' => 'Murabaha repayment',
                        default => str_replace('_', ' ', (string) $txn->type),
                    },
                    'amount' => money($txn->amount_kobo),
                    'inflow' => $txn->type === 'wallet_credit',
                    'date' => $txn->initiated_at?->toFormattedDateString(),
                ]),
        ]);
    }

    private function member(): Member
    {
        return request()->user()->member()->firstOrFail();
    }
}
