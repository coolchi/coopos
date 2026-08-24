<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Ledger\AccountingService;
use App\Enums\AccountType;
use App\Enums\VoucherType;
use App\Http\Controllers\Concerns\ResolvesRequestBranch;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingVoucher;
use App\Support\FiscalYearContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;

class VoucherController extends Controller
{
    use ResolvesRequestBranch;

    public function expenses(Request $request, AccountingService $accounting)
    {
        return $this->listing($request, $accounting, VoucherType::Expense);
    }

    public function income(Request $request, AccountingService $accounting)
    {
        return $this->listing($request, $accounting, VoucherType::Income);
    }

    public function storeExpense(Request $request, AccountingService $accounting)
    {
        return $this->store($request, $accounting, VoucherType::Expense);
    }

    public function storeIncome(Request $request, AccountingService $accounting)
    {
        return $this->store($request, $accounting, VoucherType::Income);
    }

    private function listing(Request $request, AccountingService $accounting, VoucherType $type)
    {
        $accounting->ensureChart();
        $accounting->backfillLegacyVoucherReferences();
        $branch = $this->requestBranch(null);
        $search = trim((string) $request->query('q', ''));
        $from = $request->query('from');
        $to = $request->query('to');

        $vouchers = AccountingVoucher::query()
            ->with(['account', 'contraAccount', 'createdBy', 'branch', 'journalEntry'])
            ->where('type', $type)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(
                $from || $to,
                function ($q) use ($from, $to) {
                    $q->when($from, fn ($inner) => $inner->whereDate('occurred_on', '>=', $from))
                        ->when($to, fn ($inner) => $inner->whereDate('occurred_on', '<=', $to));
                },
                fn ($q) => $q->whereBetween('occurred_on', FiscalYearContext::betweenDates())
            )
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhere('payee', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('account', function ($account) use ($search) {
                            $account->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('occurred_on')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AccountingVoucher $voucher) => [
                'uuid' => $voucher->uuid,
                'reference' => $voucher->reference,
                'occurred_on' => $voucher->occurred_on?->format('d/m/Y'),
                'account' => $voucher->account?->name,
                'account_code' => $voucher->account?->code,
                'contra' => $voucher->contraAccount?->name,
                'payee' => $voucher->payee,
                'description' => $voucher->description,
                'amount' => money($voucher->amount_kobo),
                'branch' => $voucher->branch?->name,
                'journal_uuid' => $voucher->journalEntry?->uuid,
            ]);

        $heads = Account::query()
            ->where('type', $type === VoucherType::Expense ? AccountType::Expense : AccountType::Income)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['uuid', 'code', 'name'])
            ->map(fn (Account $a) => [
                'uuid' => $a->uuid,
                'code' => $a->code,
                'name' => $a->name,
                'label' => $a->code.' · '.$a->name,
            ]);

        $cash = Account::query()
            ->where('type', AccountType::Asset)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('code', ['1000', '1010'])->orWhere('code', 'like', '10%'))
            ->orderBy('code')
            ->get(['uuid', 'code', 'name'])
            ->map(fn (Account $a) => [
                'uuid' => $a->uuid,
                'code' => $a->code,
                'name' => $a->name,
                'label' => $a->code.' · '.$a->name,
            ]);

        return Inertia::render('Tenant/Accounting/Vouchers', [
            'kind' => $type->value,
            'title' => $type === VoucherType::Expense ? 'Expenses' : 'Income',
            'vouchers' => $vouchers,
            'accounts' => $heads,
            'cash_accounts' => $cash,
            'branch' => $this->branchPayload($branch),
            'branches' => $this->branchOptions(),
            'show_branch' => $branch === null && count($this->branchOptions()) > 1,
            'filters' => [
                'q' => $search,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function store(Request $request, AccountingService $accounting, VoucherType $type)
    {
        $data = $request->validate([
            'account_uuid' => ['required', 'uuid'],
            'contra_account_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'payee' => ['nullable', 'string', 'max:120'],
            'branch_uuid' => ['nullable', 'uuid'],
        ]);
        $data['type'] = $type->value;

        try {
            $accounting->recordVoucher($data);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $type === VoucherType::Expense ? 'Expense posted.' : 'Income posted.');
    }
}
