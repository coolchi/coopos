<?php

namespace App\Domain\Ledger;

use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalEntryLine;
use App\Support\FiscalPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AccountingReportService
{
    /**
     * @return array<string, mixed>
     */
    public function profitAndLoss(FiscalPeriod $period, ?int $branchId = null, int $adminToReserveKobo = 0): array
    {
        $previous = $period->previous();
        $thisYear = $this->periodTotals($period->start, $period->end, $branchId);
        $lastYear = $this->periodTotals($previous->start, $previous->end, $branchId);

        $incomeAccounts = $this->plAccounts(AccountType::Income);
        $expenseAccounts = $this->plAccounts(AccountType::Expense);

        $income = $this->statementLines($incomeAccounts, $thisYear, $lastYear, creditNormal: true);
        $expenses = $this->statementLines($expenseAccounts, $thisYear, $lastYear, creditNormal: false);

        $totalIncomeThis = (int) $income->sum('this_year_kobo');
        $totalIncomeLast = (int) $income->sum('last_year_kobo');
        $totalExpenseThis = (int) $expenses->sum('this_year_kobo');
        $totalExpenseLast = (int) $expenses->sum('last_year_kobo');
        $surplusThis = $totalIncomeThis - $totalExpenseThis;
        $surplusLast = $totalIncomeLast - $totalExpenseLast;

        $allocable = max(0, $surplusThis);
        $profitShare = (int) round($allocable * 0.60);
        $honorarium = (int) round($allocable * 0.20);
        $reserve = $allocable - $profitShare - $honorarium;
        $adminToReserveKobo = max(0, $adminToReserveKobo);

        return [
            'year' => $period->startYear(),
            'period' => $period->rangeLabel(),
            'this_label' => $period->label(),
            'last_label' => $previous->label(),
            'income' => $income->values()->all(),
            'expenses' => $expenses->values()->all(),
            'total_income' => money($totalIncomeThis),
            'total_income_last' => money($totalIncomeLast),
            'total_income_kobo' => $totalIncomeThis,
            'total_expenditure' => money($totalExpenseThis),
            'total_expenditure_last' => money($totalExpenseLast),
            'total_expenditure_kobo' => $totalExpenseThis,
            'net_surplus' => money($surplusThis),
            'net_surplus_last' => money($surplusLast),
            'net_surplus_kobo' => $surplusThis,
            'is_loss' => $surplusThis < 0,
            'allocation' => [
                'profit_share' => money($profitShare),
                'profit_share_kobo' => $profitShare,
                'honorarium' => money($honorarium),
                'honorarium_kobo' => $honorarium,
                'reserve_fund' => money($reserve),
                'reserve_fund_kobo' => $reserve,
                'admin_to_reserve' => money($adminToReserveKobo),
                'admin_to_reserve_kobo' => $adminToReserveKobo,
                'total' => money($profitShare + $honorarium + $reserve + $adminToReserveKobo),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trialBalance(?Carbon $asOf = null, ?int $branchId = null): array
    {
        $asOf ??= now()->endOfDay();
        $totals = $this->periodTotals(Carbon::create(1970, 1, 1)->startOfDay(), $asOf, $branchId);
        $rows = Account::query()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($totals) {
                $row = $totals->get($account->id, ['debit' => 0, 'credit' => 0]);
                $debit = (int) $row['debit'];
                $credit = (int) $row['credit'];
                $net = $debit - $credit;
                $debitCol = $net > 0 ? $net : 0;
                $creditCol = $net < 0 ? abs($net) : 0;

                return [
                    'uuid' => $account->uuid,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->typeLabel(),
                    'debit' => money($debitCol),
                    'credit' => money($creditCol),
                    'debit_kobo' => $debitCol,
                    'credit_kobo' => $creditCol,
                ];
            })
            ->filter(fn (array $row) => $row['debit_kobo'] !== 0 || $row['credit_kobo'] !== 0)
            ->values();

        $debitTotal = (int) $rows->sum('debit_kobo');
        $creditTotal = (int) $rows->sum('credit_kobo');

        return [
            'as_of' => $asOf->toDateString(),
            'rows' => $rows->all(),
            'debit_total' => money($debitTotal),
            'credit_total' => money($creditTotal),
            'is_balanced' => $debitTotal === $creditTotal,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generalLedger(Account $account, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $openingRaw = (int) JournalEntryLine::query()
            ->where('account_id', $account->id)
            ->whereHas('entry', function ($q) use ($from, $branchId) {
                $q->where('status', JournalStatus::Posted)
                    ->where('posted_at', '<', $from)
                    ->when($branchId, fn ($inner) => $inner->where('branch_id', $branchId));
            })
            ->selectRaw('COALESCE(SUM(debit_kobo) - SUM(credit_kobo), 0) as balance')
            ->value('balance');

        $opening = $account->normalCreditBalance() ? -$openingRaw : $openingRaw;
        $running = $opening;

        $lines = JournalEntryLine::query()
            ->with('entry')
            ->where('account_id', $account->id)
            ->whereHas('entry', function ($q) use ($from, $to, $branchId) {
                $q->where('status', JournalStatus::Posted)
                    ->whereBetween('posted_at', [$from, $to])
                    ->when($branchId, fn ($inner) => $inner->where('branch_id', $branchId));
            })
            ->get()
            ->sortBy(fn (JournalEntryLine $line) => $line->entry?->posted_at?->timestamp.'-'.$line->id)
            ->values()
            ->map(function (JournalEntryLine $line) use ($account, &$running) {
                $movement = $account->normalCreditBalance()
                    ? (int) $line->credit_kobo - (int) $line->debit_kobo
                    : (int) $line->debit_kobo - (int) $line->credit_kobo;
                $running += $movement;

                return [
                    'date' => $line->entry?->posted_at?->toDateString(),
                    'reference' => $line->entry?->reference,
                    'uuid' => $line->entry?->uuid,
                    'description' => $line->memo ?: $line->entry?->description,
                    'debit' => money((int) $line->debit_kobo),
                    'credit' => money((int) $line->credit_kobo),
                    'balance' => money($running),
                    'debit_kobo' => (int) $line->debit_kobo,
                    'credit_kobo' => (int) $line->credit_kobo,
                ];
            });

        return [
            'account' => [
                'uuid' => $account->uuid,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->typeLabel(),
            ],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'opening' => money($opening),
            'closing' => money($running),
            'lines' => $lines->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function balanceSheet(FiscalPeriod $period, ?int $branchId = null): array
    {
        $thisYear = $this->periodTotals(Carbon::create(1970, 1, 1)->startOfDay(), $period->end, $branchId);
        $lastYear = $this->periodTotals(Carbon::create(1970, 1, 1)->startOfDay(), $period->previous()->end, $branchId);
        $plThis = $this->periodProfit($period, $branchId);
        $plLast = $this->periodProfit($period->previous(), $branchId);

        $assets = $this->positionLines(AccountType::Asset, $thisYear, $lastYear, creditNormal: false);
        $liabilities = $this->positionLines(AccountType::Liability, $thisYear, $lastYear, creditNormal: true);
        $equity = $this->positionLines(AccountType::Equity, $thisYear, $lastYear, creditNormal: true);

        $equity->push([
            'code' => '',
            'label' => 'Current year surplus / (loss)',
            'this_year' => money($plThis),
            'last_year' => money($plLast),
            'this_year_kobo' => $plThis,
            'last_year_kobo' => $plLast,
        ]);

        $assetTotal = (int) $assets->sum('this_year_kobo');
        $liabilityTotal = (int) $liabilities->sum('this_year_kobo');
        $equityTotal = (int) $equity->sum('this_year_kobo');

        return [
            'year' => $period->startYear(),
            'period' => $period->rangeLabel(),
            'as_of' => $period->end->toFormattedDateString(),
            'this_label' => $period->label(),
            'last_label' => $period->previous()->label(),
            'assets' => $assets->values()->all(),
            'liabilities' => $liabilities->values()->all(),
            'equity' => $equity->values()->all(),
            'asset_total' => money($assetTotal),
            'liability_total' => money($liabilityTotal),
            'equity_total' => money($equityTotal),
            'financed_total' => money($liabilityTotal + $equityTotal),
        ];
    }

    private function periodProfit(FiscalPeriod $period, ?int $branchId): int
    {
        $totals = $this->periodTotals($period->start, $period->end, $branchId);
        $income = 0;
        $expense = 0;

        foreach ($this->plAccounts(AccountType::Income) as $account) {
            $row = $totals->get($account->id, ['debit' => 0, 'credit' => 0]);
            $income += (int) $row['credit'] - (int) $row['debit'];
        }
        foreach ($this->plAccounts(AccountType::Expense) as $account) {
            $row = $totals->get($account->id, ['debit' => 0, 'credit' => 0]);
            $expense += (int) $row['debit'] - (int) $row['credit'];
        }

        return $income - $expense;
    }

    /**
     * @return Collection<int, Account>
     */
    private function plAccounts(AccountType $type): Collection
    {
        return Account::query()
            ->where('type', $type)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  Collection<int, array{debit: int, credit: int}>  $thisYear
     * @param  Collection<int, array{debit: int, credit: int}>  $lastYear
     * @return Collection<int, array<string, mixed>>
     */
    private function statementLines(Collection $accounts, Collection $thisYear, Collection $lastYear, bool $creditNormal): Collection
    {
        return $accounts->map(function (Account $account) use ($thisYear, $lastYear, $creditNormal) {
            $thisRow = $thisYear->get($account->id, ['debit' => 0, 'credit' => 0]);
            $lastRow = $lastYear->get($account->id, ['debit' => 0, 'credit' => 0]);
            $thisKobo = $creditNormal
                ? (int) $thisRow['credit'] - (int) $thisRow['debit']
                : (int) $thisRow['debit'] - (int) $thisRow['credit'];
            $lastKobo = $creditNormal
                ? (int) $lastRow['credit'] - (int) $lastRow['debit']
                : (int) $lastRow['debit'] - (int) $lastRow['credit'];

            return [
                'uuid' => $account->uuid,
                'code' => $account->code,
                'label' => $account->name,
                'this_year' => money($thisKobo),
                'last_year' => money($lastKobo),
                'this_year_kobo' => $thisKobo,
                'last_year_kobo' => $lastKobo,
            ];
        });
    }

    /**
     * @param  Collection<int, array{debit: int, credit: int}>  $thisYear
     * @param  Collection<int, array{debit: int, credit: int}>  $lastYear
     * @return Collection<int, array<string, mixed>>
     */
    private function positionLines(AccountType $type, Collection $thisYear, Collection $lastYear, bool $creditNormal): Collection
    {
        return Account::query()
            ->where('type', $type)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($thisYear, $lastYear, $creditNormal) {
                $thisRow = $thisYear->get($account->id, ['debit' => 0, 'credit' => 0]);
                $lastRow = $lastYear->get($account->id, ['debit' => 0, 'credit' => 0]);
                $thisKobo = $creditNormal
                    ? (int) $thisRow['credit'] - (int) $thisRow['debit']
                    : (int) $thisRow['debit'] - (int) $thisRow['credit'];
                $lastKobo = $creditNormal
                    ? (int) $lastRow['credit'] - (int) $lastRow['debit']
                    : (int) $lastRow['debit'] - (int) $lastRow['credit'];

                return [
                    'code' => $account->code,
                    'label' => $account->name,
                    'this_year' => money($thisKobo),
                    'last_year' => money($lastKobo),
                    'this_year_kobo' => $thisKobo,
                    'last_year_kobo' => $lastKobo,
                ];
            });
    }

    /**
     * @return Collection<int, array{debit: int, credit: int}>
     */
    private function periodTotals(Carbon $from, Carbon $to, ?int $branchId): Collection
    {
        return JournalEntryLine::query()
            ->whereHas('entry', function ($q) use ($from, $to, $branchId) {
                $q->where('status', JournalStatus::Posted)
                    ->whereBetween('posted_at', [$from, $to])
                    ->when($branchId, fn ($inner) => $inner->where('branch_id', $branchId));
            })
            ->selectRaw('account_id, COALESCE(SUM(debit_kobo), 0) as debit, COALESCE(SUM(credit_kobo), 0) as credit')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id')
            ->map(fn ($row) => ['debit' => (int) $row->debit, 'credit' => (int) $row->credit]);
    }
}
