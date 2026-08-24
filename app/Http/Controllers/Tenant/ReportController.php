<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Ledger\AccountingReportService;
use App\Domain\Ledger\AccountingService;
use App\Domain\Reporting\StatementService;
use App\Domain\Savings\ContributionService;
use App\Enums\ContributionBucket;
use App\Http\Controllers\Concerns\ResolvesRequestBranch;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Contribution;
use App\Models\ContributionProduct;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MurabahaFinancing;
use App\Models\QardHasanFinancing;
use App\Models\WithdrawalRequest;
use App\Support\FiscalPeriod;
use App\Support\FiscalYearContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class ReportController extends Controller
{
    use ResolvesRequestBranch;

    public function index()
    {
        [$fyFrom, $fyTo] = FiscalYearContext::betweenDates();
        $period = FiscalYearContext::period();
        $monthStart = max($period->start->toDateString(), now()->startOfMonth()->toDateString());
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();
        if ($lastMonthStart->lt($period->start)) {
            $lastMonthStart = $period->start->copy();
        }
        if ($lastMonthEnd->gt($period->end)) {
            $lastMonthEnd = $period->end->copy();
        }

        $monthly = collect(range(5, 0))->map(function (int $i) use ($period) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            if ($end->lt($period->start) || $start->gt($period->end)) {
                return [
                    'label' => $start->format('M'),
                    'year' => $start->format('Y'),
                    'amount' => money(0),
                    'amount_kobo' => 0,
                ];
            }
            $from = max($start->toDateString(), $period->start->toDateString());
            $to = min($end->toDateString(), $period->end->toDateString());
            $kobo = (int) Contribution::query()
                ->whereBetween('paid_on', [$from, $to])
                ->sum('amount_kobo');

            return [
                'label' => $start->format('M'),
                'year' => $start->format('Y'),
                'amount' => money($kobo),
                'amount_kobo' => $kobo,
            ];
        });
        $peak = max(1, ...$monthly->pluck('amount_kobo'));

        $topSavers = Contribution::query()
            ->selectRaw('member_id, sum(amount_kobo) as total_kobo')
            ->whereBetween('paid_on', [$fyFrom, $fyTo])
            ->groupBy('member_id')
            ->orderByDesc('total_kobo')
            ->limit(8)
            ->with('member')
            ->get()
            ->map(fn ($row) => [
                'member' => $row->member?->fullName() ?? '—',
                'membership_number' => $row->member?->membership_number,
                'total' => money((int) $row->total_kobo),
            ]);

        $qardByStatusQuery = QardHasanFinancing::query()
            ->selectRaw('status, count(*) as facilities, coalesce(sum(outstanding_kobo), 0) as outstanding_kobo')
            ->groupBy('status');
        FiscalYearContext::constrainDateOrCreated($qardByStatusQuery, 'disbursed_on');
        $qardByStatus = $qardByStatusQuery->get()
            ->map(fn ($row) => [
                'status' => is_string($row->status) ? $row->status : $row->status->value,
                'facilities' => (int) $row->facilities,
                'outstanding' => money((int) $row->outstanding_kobo),
            ]);

        $murabahaByStatusQuery = MurabahaFinancing::query()
            ->selectRaw('status, count(*) as facilities, coalesce(sum(outstanding_kobo), 0) as outstanding_kobo')
            ->groupBy('status');
        FiscalYearContext::constrainDateOrCreated($murabahaByStatusQuery, 'disbursed_on');
        $murabahaByStatus = $murabahaByStatusQuery->get()
            ->map(fn ($row) => [
                'status' => is_string($row->status) ? $row->status : $row->status->value,
                'facilities' => (int) $row->facilities,
                'outstanding' => money((int) $row->outstanding_kobo),
            ]);

        $loansByStatusQuery = Loan::query()
            ->selectRaw('status, count(*) as facilities, coalesce(sum(outstanding_kobo), 0) as outstanding_kobo')
            ->groupBy('status');
        FiscalYearContext::constrainDateOrCreated($loansByStatusQuery, 'disbursed_on');
        $loansByStatus = $loansByStatusQuery->get()
            ->map(fn ($row) => [
                'status' => is_string($row->status) ? $row->status : $row->status->value,
                'facilities' => (int) $row->facilities,
                'outstanding' => money((int) $row->outstanding_kobo),
            ]);

        $loanPortfolio = Loan::query();
        FiscalYearContext::constrainDateOrCreated($loanPortfolio, 'disbursed_on');
        $qardPortfolio = QardHasanFinancing::query();
        FiscalYearContext::constrainDateOrCreated($qardPortfolio, 'disbursed_on');
        $murabahaPortfolio = MurabahaFinancing::query();
        FiscalYearContext::constrainDateOrCreated($murabahaPortfolio, 'disbursed_on');
        $withdrawals = WithdrawalRequest::query();
        FiscalYearContext::constrain($withdrawals, 'created_at');

        return Inertia::render('Tenant/Reports/Index', [
            'generated_at' => now()->toDayDateTimeString(),
            'membership' => [
                'all' => Member::query()->count(),
                'active' => Member::query()->where('status', 'active')->count(),
                'pending' => Member::query()->where('status', 'pending')->count(),
                'suspended' => Member::query()->where('status', 'suspended')->count(),
                'new_this_month' => Member::query()->whereBetween('created_at', [max($monthStart, $fyFrom), $fyTo.' 23:59:59'])->count(),
            ],
            'savings' => [
                'lifetime' => money((int) Contribution::query()->whereBetween('paid_on', [$fyFrom, $fyTo])->sum('amount_kobo')),
                'this_month' => money((int) Contribution::query()->where('paid_on', '>=', $monthStart)->where('paid_on', '<=', $fyTo)->sum('amount_kobo')),
                'last_month' => money((int) Contribution::query()->whereBetween('paid_on', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])->sum('amount_kobo')),
                'payments_this_month' => Contribution::query()->where('paid_on', '>=', $monthStart)->where('paid_on', '<=', $fyTo)->count(),
                'lifetime_payments' => Contribution::query()->whereBetween('paid_on', [$fyFrom, $fyTo])->count(),
            ],
            'financing' => [
                'loan_portfolio' => money((int) (clone $loanPortfolio)->sum('outstanding_kobo')),
                'qard_portfolio' => money((int) (clone $qardPortfolio)->sum('outstanding_kobo')),
                'murabaha_portfolio' => money((int) (clone $murabahaPortfolio)->sum('outstanding_kobo')),
                'delinquent_loans' => Loan::query()->whereHas('repayments', fn ($q) => $q->where('status', 'pending')->whereDate('due_on', '<', now())->whereBetween('due_on', [$fyFrom, $fyTo]))->count(),
                'active_qard' => (clone $qardPortfolio)->where('status', 'active')->count(),
                'active_murabaha' => (clone $murabahaPortfolio)->where('status', 'active')->count(),
            ],
            'withdrawals' => [
                'pending' => (clone $withdrawals)->where('status', 'pending')->count(),
                'approved' => (clone $withdrawals)->where('status', 'approved')->count(),
                'total' => money((int) (clone $withdrawals)->sum('amount_kobo')),
            ],
            'monthly' => $monthly->map(fn ($row) => [
                ...$row,
                'pct' => (int) round(($row['amount_kobo'] / $peak) * 100),
            ]),
            'top_savers' => $topSavers,
            'qard_by_status' => $qardByStatus,
            'murabaha_by_status' => $murabahaByStatus,
            'loans_by_status' => $loansByStatus,
            'recent_contributions' => Contribution::query()->with('member', 'product')->whereBetween('paid_on', [$fyFrom, $fyTo])->latest('paid_on')->limit(8)->get()
                ->map(fn ($c) => [
                    'member' => $c->member?->fullName(),
                    'product' => $c->product?->name,
                    'amount' => money($c->amount_kobo),
                    'paid_on' => $c->paid_on?->toDateString(),
                ]),
        ]);
    }

    public function statement(Request $request, StatementService $statements)
    {
        $members = Member::query()->orderBy('first_name')->get(['uuid', 'first_name', 'last_name', 'membership_number']);
        $period = FiscalYearContext::period();
        $filters = [
            'member_uuid' => $request->input('member_uuid'),
            'from' => $request->input('from', $period->start->toDateString()),
            'to' => $request->input('to', min($period->end->toDateString(), now()->toDateString())),
        ];

        $statement = null;
        if ($request->filled('member_uuid')) {
            $data = $request->validate([
                'member_uuid' => ['required'],
                'from' => ['required', 'date'],
                'to' => ['required', 'date'],
            ]);
            $member = Member::query()->where('uuid', $data['member_uuid'])->firstOrFail();
            $statement = $statements->memberStatement($member, $data['from'], $data['to']);
        }

        if ($request->wantsJson() && $statement) {
            return response()->json($statement);
        }

        return Inertia::render('Tenant/Reports/Statement', [
            'statement' => $statement,
            'members' => $members,
            'filters' => $filters,
        ]);
    }

    public function accounts(Request $request, AccountingService $accounting, AccountingReportService $reports)
    {
        $accounting->ensureChart();
        $period = FiscalPeriod::forYear($request->integer('year') ?: null);
        $branch = $this->requestBranch($request->query('branch'));
        $sheet = $reports->balanceSheet($period, $branch?->id);

        return Inertia::render('Tenant/Reports/Accounts', [
            ...$sheet,
            'branch' => $this->branchPayload($branch),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function profitLoss(Request $request, AccountingService $accounting, AccountingReportService $reports)
    {
        $accounting->ensureChart();
        $period = FiscalPeriod::forYear($request->integer('year') ?: null);
        $branch = $this->requestBranch($request->query('branch'));
        $admin = naira_to_kobo($request->input('admin_to_reserve') ?: 0);
        $statement = $reports->profitAndLoss($period, $branch?->id, $admin);

        return Inertia::render('Tenant/Reports/ProfitLoss', [
            ...$statement,
            'hide_zeros' => $request->boolean('hide_zeros'),
            'branch' => $this->branchPayload($branch),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function trialBalance(Request $request, AccountingService $accounting, AccountingReportService $reports)
    {
        $accounting->ensureChart();
        $asOf = $request->query('as_of')
            ? Carbon::parse($request->query('as_of'))->endOfDay()
            : FiscalYearContext::period()->end->copy()->min(now()->endOfDay());
        $branch = $this->requestBranch($request->query('branch'));

        return Inertia::render('Tenant/Reports/TrialBalance', [
            ...$reports->trialBalance($asOf, $branch?->id),
            'branch' => $this->branchPayload($branch),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function ledger(Request $request, AccountingService $accounting, AccountingReportService $reports)
    {
        $accounting->ensureChart();
        $period = FiscalPeriod::forYear($request->integer('year') ?: null);
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : $period->start;
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : $period->end;
        $branch = $this->requestBranch($request->query('branch'));
        $accounts = Account::query()->orderBy('code')->get(['uuid', 'code', 'name']);
        $account = $request->query('account')
            ? Account::query()->where('uuid', $request->query('account'))->first()
            : $accounts->first();

        $ledger = $account
            ? $reports->generalLedger($account, $from, $to, $branch?->id)
            : null;

        return Inertia::render('Tenant/Reports/Ledger', [
            'ledger' => $ledger,
            'accounts' => $accounts->map(fn (Account $a) => [
                'uuid' => $a->uuid,
                'code' => $a->code,
                'name' => $a->name,
                'label' => $a->code.' · '.$a->name,
            ]),
            'filters' => [
                'account' => $account?->uuid,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch' => $branch?->uuid,
            ],
            'branch' => $this->branchPayload($branch),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function withdrawals(Request $request)
    {
        $month = $this->monthFilter($request->query('month'));
        $withdrawals = WithdrawalRequest::query()->with(['member', 'product', 'bankAccount']);
        FiscalYearContext::constrain($withdrawals, 'created_at');

        if ($month) {
            $withdrawals->whereBetween(
                $withdrawals->getModel()->qualifyColumn('created_at'),
                [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()],
            );
        }

        return Inertia::render('Tenant/Savings/Withdrawals', [
            'month' => $month?->format('Y-m') ?? '',
            'months' => $this->withdrawalMonthOptions(),
            'withdrawals' => $withdrawals->latest('id')->paginate(20)
                ->withQueryString()
                ->through(fn ($w) => [
                    'uuid' => $w->uuid,
                    'member' => $w->member->fullName(),
                    'member_uuid' => $w->member->uuid,
                    'product' => $w->product?->name,
                    'product_uuid' => $w->product?->uuid,
                    'amount' => money($w->amount_kobo),
                    'amount_naira' => round($w->amount_kobo / 100, 2),
                    'reason' => $w->reason,
                    'status' => $w->status,
                    'requested_on' => $w->created_at?->timezone(config('app.timezone'))->format('d M Y'),
                    'bank_account_uuid' => $w->bankAccount?->uuid,
                    'sent_to' => $w->bankAccount?->bank_name,
                    'sent_to_account' => $w->bankAccount?->account_number,
                    'sent_to_name' => $w->bankAccount?->account_name,
                ]),
            'members' => Member::query()
                ->where('status', 'active')
                ->with('bankAccounts')
                ->orderBy('first_name')
                ->get(['id', 'uuid', 'first_name', 'last_name', 'membership_number'])
                ->map(fn (Member $m) => [
                    'uuid' => $m->uuid,
                    'first_name' => $m->first_name,
                    'last_name' => $m->last_name,
                    'membership_number' => $m->membership_number,
                    'bank_accounts' => $m->bankAccounts->map(fn ($a) => [
                        'uuid' => $a->uuid,
                        'label' => $a->bank_name.' · '.$a->account_number,
                        'bank_name' => $a->bank_name,
                        'account_number' => $a->account_number,
                        'account_name' => $a->account_name,
                        'is_primary' => (bool) $a->is_primary,
                    ])->values(),
                ]),
            'products' => ContributionProduct::query()
                ->where('is_active', true)
                ->where('bucket', ContributionBucket::SpecialTarget)
                ->where('is_locked', false)
                ->get(['uuid', 'name']),
            'can_manage_withdrawals' => (bool) request()->user()?->can('contributions.manage'),
        ]);
    }

    public function requestWithdrawal(Request $request, ContributionService $service)
    {
        $data = $request->validate([
            'member_uuid' => ['required'],
            'product_uuid' => ['required'],
            'amount' => ['required', 'numeric'],
            'reason' => ['nullable', 'string'],
            'bank_account_uuid' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'account_number' => ['nullable', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $member = Member::query()->where('uuid', $data['member_uuid'])->firstOrFail();
            $bank = $service->resolvePayoutAccount(
                $member,
                $data['bank_account_uuid'] ?? null,
                [
                    'bank_name' => $data['bank_name'] ?? '',
                    'account_number' => $data['account_number'] ?? '',
                    'account_name' => $data['account_name'] ?? '',
                ],
            );
            $service->requestWithdrawal(
                $member,
                ContributionProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail(),
                naira_to_kobo($data['amount']),
                $data['reason'] ?? '',
                $bank,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Withdrawal request submitted.');
    }

    public function updateWithdrawal(Request $request, string $tenant, WithdrawalRequest $withdrawal, ContributionService $service)
    {
        abort_unless($request->user()?->can('contributions.manage'), 403);

        $data = $request->validate([
            'access_code' => ['required', 'string'],
            'member_uuid' => ['required', 'uuid'],
            'product_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['nullable', 'string'],
            'bank_account_uuid' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'account_number' => ['nullable', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:120'],
        ], [
            'access_code.required' => 'Enter the access code.',
            'amount.min' => 'Amount must be at least ₦1.',
        ]);

        $this->assertTransactionAccessCode($data['access_code']);

        try {
            $member = Member::query()->where('uuid', $data['member_uuid'])->firstOrFail();
            $updateBank = array_key_exists('bank_account_uuid', $data)
                || filled($data['bank_name'] ?? null)
                || filled($data['account_number'] ?? null);
            $bank = $updateBank
                ? $service->resolvePayoutAccount(
                    $member,
                    $data['bank_account_uuid'] ?? null,
                    [
                        'bank_name' => $data['bank_name'] ?? '',
                        'account_number' => $data['account_number'] ?? '',
                        'account_name' => $data['account_name'] ?? '',
                    ],
                )
                : null;
            $service->updateWithdrawal(
                $withdrawal,
                $member,
                ContributionProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail(),
                naira_to_kobo($data['amount']),
                $data['reason'] ?? '',
                $bank,
                $updateBank,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Withdrawal updated.');
    }

    public function destroyWithdrawal(Request $request, string $tenant, WithdrawalRequest $withdrawal, ContributionService $service)
    {
        abort_unless($request->user()?->can('contributions.manage'), 403);

        $data = $request->validate([
            'access_code' => ['required', 'string'],
        ], [
            'access_code.required' => 'Enter the access code.',
        ]);

        $this->assertTransactionAccessCode($data['access_code']);

        try {
            $service->deleteWithdrawal($withdrawal);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Withdrawal deleted.');
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

    private function monthFilter(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            $month = Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
        } catch (\Throwable) {
            return null;
        }

        [$start, $end] = FiscalYearContext::between();
        if ($month->lt($start->copy()->startOfMonth()) || $month->gt($end->copy()->startOfMonth())) {
            return null;
        }

        return $month;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function withdrawalMonthOptions(): array
    {
        [$start, $end] = FiscalYearContext::between();
        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();
        $options = [['value' => '', 'label' => 'All months']];

        while ($cursor->lte($last)) {
            $options[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->format('F Y'),
            ];
            $cursor->addMonth();
        }

        return $options;
    }
}
