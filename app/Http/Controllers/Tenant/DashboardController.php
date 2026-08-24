<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Reporting\StatementService;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Branch;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MurabahaFinancing;
use App\Models\QardHasanFinancing;
use App\Support\BranchContext;
use App\Support\FiscalYearContext;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(StatementService $statements)
    {
        $kpis = $statements->dashboardKpis();
        $tenant = tenant();

        $recentLoansQuery = Loan::query()->with('member');
        FiscalYearContext::constrainDateOrCreated($recentLoansQuery, 'disbursed_on');
        $recentLoans = $recentLoansQuery->latest()->limit(5)->get()
            ->map(fn ($l) => [
                'uuid' => $l->uuid,
                'kind' => 'loan',
                'href' => tenant_path('/loans/'.$l->uuid),
                'member' => $l->member->fullName(),
                'amount' => money($l->principal_kobo),
                'status' => $l->status->value,
            ]);

        $recentQardQuery = QardHasanFinancing::query()->with('member');
        FiscalYearContext::constrainDateOrCreated($recentQardQuery, 'disbursed_on');
        $recentQard = $recentQardQuery->latest()->limit(5)->get()
            ->map(fn ($f) => [
                'uuid' => $f->uuid,
                'kind' => 'qard_hasan',
                'href' => tenant_path('/loan'),
                'member' => $f->member->fullName(),
                'amount' => money($f->principal_kobo),
                'status' => $f->status->value,
            ]);

        $recentMurabahaQuery = MurabahaFinancing::query()->with('member');
        FiscalYearContext::constrainDateOrCreated($recentMurabahaQuery, 'disbursed_on');
        $recentMurabaha = $recentMurabahaQuery->latest()->limit(5)->get()
            ->map(fn ($f) => [
                'uuid' => $f->uuid,
                'kind' => 'murabaha',
                'href' => tenant_path('/loan'),
                'member' => $f->member->fullName(),
                'amount' => money($f->sale_price_kobo),
                'status' => $f->status->value,
            ]);

        $recent = collect()
            ->concat($tenant->offersConventional() ? $recentLoans : [])
            ->concat($tenant->offersIslamic() ? $recentQard : [])
            ->concat($tenant->offersIslamic() ? $recentMurabaha : [])
            ->take(8)
            ->values();

        $pendingQuery = ApprovalRequest::query()->where('status', 'pending');
        FiscalYearContext::constrain($pendingQuery, 'created_at');

        return Inertia::render('Tenant/Dashboard', [
            'kpis' => [
                ...$kpis,
                'savings' => money($kpis['savings_kobo']),
                'loans_outstanding' => money($kpis['loans_outstanding_kobo']),
                'islamic_outstanding' => money($kpis['qard_outstanding_kobo'] + $kpis['murabaha_outstanding_kobo']),
            ],
            'pending_approvals' => (clone $pendingQuery)->count(),
            'pending_rows' => (clone $pendingQuery)->with(['workflow', 'approvable'])->latest()->limit(5)->get()
                ->map(fn ($r) => [
                    'uuid' => $r->uuid,
                    'workflow' => $this->workflowLabel($r->workflow?->name, $r->workflow?->applies_to),
                    'type' => $this->approvableLabel($r->approvable_type),
                    'step' => $r->current_step,
                ]),
            'recent_financing' => $recent,
            'branches' => $this->branchSnapshot(),
            'form_branches' => $this->memberFormBranches(),
            'states' => config('coopos.nigeria_states'),
            'introducers' => Member::query()->orderBy('first_name')->get(['uuid', 'first_name', 'last_name', 'membership_number']),
            'locked_branch' => BranchContext::isConstrained(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function branchSnapshot(): ?array
    {
        $user = request()->user();
        if (! $user?->isCentralAdmin()) {
            return null;
        }

        $branches = Branch::query()
            ->with('admin')
            ->withCount([
                'members',
                'staff' => fn ($q) => $q->where('type', UserType::Staff),
            ])
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get();

        $contributionTotals = Contribution::query()
            ->selectRaw('branch_id, coalesce(sum(amount_kobo), 0) as total_kobo')
            ->whereBetween('paid_on', FiscalYearContext::betweenDates())
            ->groupBy('branch_id')
            ->pluck('total_kobo', 'branch_id');

        return $branches
            ->map(fn ($b) => [
                'uuid' => $b->uuid,
                'name' => $b->name,
                'code' => $b->code,
                'is_headquarters' => $b->is_headquarters,
                'members_count' => $b->members_count,
                'staff_count' => $b->staff_count,
                'payments_total' => money((int) ($contributionTotals[$b->id] ?? 0)),
                'admin' => $b->admin?->name,
            ])
            ->all();
    }

    /**
     * @return list<array{uuid: string, name: string, code: string}>
     */
    private function memberFormBranches(): array
    {
        if (! Schema::hasTable('branches')) {
            return [];
        }

        return Branch::query()->where('is_active', true)->orderByDesc('is_headquarters')->orderBy('name')
            ->get(['uuid', 'name', 'code'])
            ->map(fn (Branch $b) => [
                'uuid' => $b->uuid,
                'name' => $b->name,
                'code' => $b->code,
            ])
            ->all();
    }

    private function workflowLabel(?string $name, ?string $appliesTo): string
    {
        if ($appliesTo === 'islamic_financing' || in_array($name, ['Islamic financing', 'Loans'], true)) {
            return 'Loan financing';
        }

        return $name ?: 'Approval';
    }

    private function approvableLabel(?string $type): string
    {
        return match (class_basename((string) $type)) {
            'LoanApplication' => 'Loan',
            'WithdrawalRequest' => 'Withdrawal',
            'QardHasanFinancing' => 'Qard Hasan',
            'MurabahaFinancing' => 'Murabaha',
            default => class_basename((string) $type) ?: 'Request',
        };
    }
}
