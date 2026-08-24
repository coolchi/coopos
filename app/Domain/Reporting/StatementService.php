<?php

namespace App\Domain\Reporting;

use App\Domain\Ledger\LedgerService;
use App\Models\Contribution;
use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MurabahaFinancing;
use App\Models\QardHasanFinancing;
use App\Support\FiscalYearContext;
use Carbon\Carbon;

class StatementService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function memberStatement(Member $member, string $from, string $to): array
    {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        $txns = FinancialTransaction::query()
            ->where('member_id', $member->id)
            ->whereBetween('completed_at', [$fromDate, $toDate])
            ->orderBy('completed_at')
            ->get();

        $opening = (int) FinancialTransaction::query()
            ->where('member_id', $member->id)
            ->where('completed_at', '<', $fromDate)
            ->where('type', 'contribution')
            ->sum('amount_kobo')
            - (int) FinancialTransaction::query()
                ->where('member_id', $member->id)
                ->where('completed_at', '<', $fromDate)
                ->where('type', 'withdrawal')
                ->sum('amount_kobo');

        $lines = [];
        $running = $opening;

        foreach ($txns as $txn) {
            $inflow = $this->isInflow($txn->type);
            $delta = $inflow ? $txn->amount_kobo : -$txn->amount_kobo;
            $running += $delta;
            $lines[] = [
                'date' => $txn->completed_at?->toDateString(),
                'date_label' => $txn->completed_at?->format('j M Y'),
                'reference' => $txn->reference,
                'type' => $txn->type,
                'label' => $this->lineLabel($txn->type),
                'inflow' => $inflow,
                'amount_kobo' => $txn->amount_kobo,
                'amount' => money($txn->amount_kobo),
                'running_kobo' => $running,
                'running' => money(max(0, $running)),
            ];
        }

        return [
            'member' => $member->fullName(),
            'membership_number' => $member->membership_number,
            'period' => ['from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()],
            'period_label' => $fromDate->format('j M Y').' – '.$toDate->format('j M Y'),
            'opening_kobo' => $opening,
            'opening' => money(max(0, $opening)),
            'closing_kobo' => $running,
            'closing' => money(max(0, $running)),
            'savings_balance' => money($this->ledger->memberSavingsBalance($member)),
            'generated_at' => now()->toDateTimeString(),
            'reference' => 'STMT-'.$member->membership_number.'-'.$fromDate->format('Ymd'),
            'lines' => $lines,
        ];
    }

    private function isInflow(string $type): bool
    {
        return in_array($type, [
            'contribution',
            'wallet_credit',
            'loan_disbursement',
            'qard_hasan_disbursement',
            'murabaha_sale',
        ], true);
    }

    private function lineLabel(string $type): string
    {
        return match ($type) {
            'contribution' => 'Contribution',
            'withdrawal' => 'Withdrawal',
            'wallet_credit' => 'Wallet credit',
            'loan_disbursement' => 'Loan disbursement',
            'loan_repayment' => 'Loan repayment',
            'qard_hasan_disbursement' => 'Qard Hasan',
            'qard_hasan_admin_fee' => 'Admin fee',
            'qard_hasan_repayment' => 'Qard Hasan repayment',
            'murabaha_sale' => 'Murabaha',
            'murabaha_repayment' => 'Murabaha repayment',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function dashboardKpis(): array
    {
        [$start, $end] = FiscalYearContext::between();
        [$from, $to] = FiscalYearContext::betweenDates();

        $loans = Loan::query()->whereIn('status', ['active', 'disbursed']);
        FiscalYearContext::constrainDateOrCreated($loans, 'disbursed_on');

        $qard = QardHasanFinancing::query()->where('status', 'active');
        FiscalYearContext::constrainDateOrCreated($qard, 'disbursed_on');

        $murabaha = MurabahaFinancing::query()->where('status', 'active');
        FiscalYearContext::constrainDateOrCreated($murabaha, 'disbursed_on');

        return [
            'members' => Member::query()->count(),
            'active_members' => Member::query()->where('status', 'active')->count(),
            'new_members' => Member::query()->whereBetween('created_at', [$start, $end])->count(),
            'savings_kobo' => (int) Contribution::query()->whereBetween('paid_on', [$from, $to])->sum('amount_kobo'),
            'loans_outstanding_kobo' => (int) $loans->sum('outstanding_kobo'),
            'qard_outstanding_kobo' => (int) $qard->sum('outstanding_kobo'),
            'murabaha_outstanding_kobo' => (int) $murabaha->sum('outstanding_kobo'),
            'overdue_loans' => Loan::query()
                ->whereHas('repayments', fn ($q) => $q->where('status', 'pending')->whereDate('due_on', '<', now())->whereBetween('due_on', [$from, $to]))
                ->count(),
        ];
    }
}
