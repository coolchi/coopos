<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Ledger\AccountingService;
use App\Http\Controllers\Concerns\ResolvesRequestBranch;
use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Support\FiscalYearContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JournalController extends Controller
{
    use ResolvesRequestBranch;

    public function index(Request $request, AccountingService $accounting)
    {
        $accounting->ensureChart();
        $branch = $this->requestBranch($request->query('branch'));

        $journals = JournalEntry::query()
            ->with(['lines', 'branch'])
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(
                $request->query('from') || $request->query('to'),
                function ($q) use ($request) {
                    $q->when($request->query('from'), fn ($inner, $from) => $inner->whereDate('posted_at', '>=', $from))
                        ->when($request->query('to'), fn ($inner, $to) => $inner->whereDate('posted_at', '<=', $to));
                },
                fn ($q) => $q->whereBetween('posted_at', FiscalYearContext::between())
            )
            ->when($request->query('q'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest('posted_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => [
                'uuid' => $entry->uuid,
                'reference' => $entry->reference,
                'description' => $entry->description,
                'posted_at' => $entry->posted_at?->toDateString(),
                'status' => $entry->status?->value ?? (string) $entry->status,
                'debit' => money((int) $entry->lines->sum('debit_kobo')),
                'credit' => money((int) $entry->lines->sum('credit_kobo')),
                'branch' => $entry->branch?->name,
                'lines' => $entry->lines->count(),
            ]);

        return Inertia::render('Tenant/Accounting/Journals', [
            'journals' => $journals,
            'branch' => $this->branchPayload($branch),
            'branches' => $this->branchOptions(),
            'filters' => [
                'q' => $request->query('q'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'branch' => $branch?->uuid,
            ],
        ]);
    }

    public function show(string $tenant, JournalEntry $journal)
    {
        $journal->load(['lines.account', 'createdBy', 'branch']);

        return Inertia::render('Tenant/Accounting/JournalShow', [
            'journal' => [
                'uuid' => $journal->uuid,
                'reference' => $journal->reference,
                'description' => $journal->description,
                'posted_at' => $journal->posted_at?->toDayDateTimeString(),
                'status' => $journal->status?->value ?? (string) $journal->status,
                'branch' => $journal->branch?->name,
                'created_by' => $journal->createdBy?->name,
                'debit' => money((int) $journal->lines->sum('debit_kobo')),
                'credit' => money((int) $journal->lines->sum('credit_kobo')),
                'lines' => $journal->lines->map(fn ($line) => [
                    'account' => $line->account?->name,
                    'code' => $line->account?->code,
                    'memo' => $line->memo,
                    'debit' => money((int) $line->debit_kobo),
                    'credit' => money((int) $line->credit_kobo),
                ]),
            ],
        ]);
    }
}
