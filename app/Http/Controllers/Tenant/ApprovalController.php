<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Approvals\ApprovalService;
use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Domain\Lending\LoanService;
use App\Domain\Savings\ContributionService;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\LoanApplication;
use App\Models\MurabahaFinancing;
use App\Models\QardHasanFinancing;
use App\Models\WithdrawalRequest;
use App\Support\FiscalYearContext;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApprovalController extends Controller
{
    public function index()
    {
        $requests = ApprovalRequest::query()
            ->with([
                'workflow.steps',
                'requester',
                'actions.user',
                'actions.step',
                'approvable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        LoanApplication::class => ['member.branch', 'product'],
                        WithdrawalRequest::class => ['member.branch', 'product'],
                        QardHasanFinancing::class => ['member.branch', 'product'],
                        MurabahaFinancing::class => ['member.branch', 'product'],
                    ]);
                },
            ])
            ->where('status', ApprovalStatus::Pending);
        FiscalYearContext::constrain($requests, 'created_at');
        $requests = $requests
            ->latest()
            ->get()
            ->map(fn (ApprovalRequest $request) => $this->present($request))
            ->values();

        return Inertia::render('Tenant/Approvals/Index', [
            'requests' => $requests,
            'counts' => [
                'all' => $requests->count(),
                'loan' => $requests->where('category', 'loan')->count(),
                'withdrawal' => $requests->where('category', 'withdrawal')->count(),
            ],
        ]);
    }

    public function act(Request $request, string $tenant, ApprovalRequest $approval, ApprovalService $approvals, LoanService $loans, ContributionService $savings, IslamicFinancingService $islamic)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['decision'] === 'rejected' && blank($data['comment'] ?? null)) {
            return back()->withErrors([
                'comment' => 'Add a short reason when rejecting.',
            ]);
        }

        $result = $approvals->act(
            $approval,
            $request->user(),
            $data['decision'] === 'approved' ? ApprovalStatus::Approved : ApprovalStatus::Rejected,
            $data['comment'] ?? null,
        );

        if ($result->status === ApprovalStatus::Approved && $result->approvable instanceof LoanApplication) {
            $loans->disburse($result->approvable);
        }

        if ($result->status === ApprovalStatus::Approved && $result->approvable instanceof WithdrawalRequest) {
            $savings->approveWithdrawal($result->approvable);
        }

        if ($result->status === ApprovalStatus::Approved && $result->approvable instanceof QardHasanFinancing) {
            $islamic->disburseQardHasan($result->approvable);
        }

        if ($result->status === ApprovalStatus::Approved && $result->approvable instanceof MurabahaFinancing) {
            $islamic->finalizeMurabahaSale($result->approvable);
        }

        return back()->with('success', 'Approval recorded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ApprovalRequest $request): array
    {
        $workflow = $request->workflow;
        $steps = $workflow?->steps ?? collect();
        $step = $steps->firstWhere('position', $request->current_step);
        $detail = $this->approvableDetail($request->approvable);

        return [
            'uuid' => $request->uuid,
            'workflow' => $this->workflowLabel($workflow?->name, $workflow?->applies_to),
            'category' => $detail['category'],
            'type' => $detail['type'],
            'type_label' => $detail['type_label'],
            'status' => $request->status->value,
            'amount' => $detail['amount'],
            'amount_label' => $detail['amount_label'],
            'member' => $detail['member'],
            'member_number' => $detail['member_number'],
            'member_href' => $detail['member_href'],
            'branch' => $detail['branch'],
            'product' => $detail['product'],
            'purpose' => $detail['purpose'],
            'meta' => $detail['meta'],
            'href' => $detail['href'],
            'step' => $request->current_step,
            'steps_total' => $steps->count(),
            'step_name' => $step?->name ?? ('Step '.$request->current_step),
            'step_role' => $step?->role_name,
            'requested_by' => $request->requester?->name,
            'requested_at' => $request->created_at?->timezone(config('app.timezone'))->format('d M Y · H:i'),
            'history' => $request->actions
                ->sortBy('created_at')
                ->values()
                ->map(fn ($action) => [
                    'step' => $action->step?->name ?? 'Step',
                    'decision' => $action->decision?->value ?? 'approved',
                    'by' => $action->user?->name ?? 'Officer',
                    'comment' => $action->comment,
                    'at' => $action->created_at?->timezone(config('app.timezone'))->format('d M Y · H:i'),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{
     *     category: string,
     *     type: string,
     *     type_label: string,
     *     amount: ?string,
     *     amount_label: string,
     *     member: ?string,
     *     member_number: ?string,
     *     member_href: ?string,
     *     branch: ?string,
     *     product: ?string,
     *     purpose: ?string,
     *     meta: list<string>,
     *     href: ?string
     * }
     */
    private function approvableDetail(mixed $approvable): array
    {
        $empty = [
            'category' => 'other',
            'type' => 'request',
            'type_label' => 'Request',
            'amount' => null,
            'amount_label' => 'Amount',
            'member' => null,
            'member_number' => null,
            'member_href' => null,
            'branch' => null,
            'product' => null,
            'purpose' => null,
            'meta' => [],
            'href' => null,
        ];

        if ($approvable instanceof LoanApplication) {
            $member = $approvable->member;

            return [
                ...$empty,
                'category' => 'loan',
                'type' => 'loan',
                'type_label' => 'Loan',
                'amount' => money((int) $approvable->amount_kobo),
                'amount_label' => 'Amount',
                'member' => $member?->fullName(),
                'member_number' => $member?->membership_number,
                'member_href' => $member ? tenant_path('members/'.$member->uuid) : null,
                'branch' => $member?->branch?->name,
                'product' => $approvable->product?->name,
                'purpose' => $approvable->purpose,
                'meta' => array_values(array_filter([
                    $approvable->tenure_months ? $approvable->tenure_months.' months' : null,
                    $approvable->estimated_repayment_kobo
                        ? 'Est. repayment '.money((int) $approvable->estimated_repayment_kobo)
                        : null,
                ])),
                'href' => tenant_path('loans/applications'),
            ];
        }

        if ($approvable instanceof WithdrawalRequest) {
            $member = $approvable->member;

            return [
                ...$empty,
                'category' => 'withdrawal',
                'type' => 'withdrawal',
                'type_label' => 'Withdrawal',
                'amount' => money((int) $approvable->amount_kobo),
                'amount_label' => 'Withdrawal amount',
                'member' => $member?->fullName(),
                'member_number' => $member?->membership_number,
                'member_href' => $member ? tenant_path('members/'.$member->uuid) : null,
                'branch' => $member?->branch?->name,
                'product' => $approvable->product?->name,
                'purpose' => $approvable->reason,
                'meta' => array_values(array_filter([
                    $approvable->type ? str_replace('_', ' ', $approvable->type) : null,
                    $approvable->fee_kobo ? 'Fee '.money((int) $approvable->fee_kobo) : null,
                ])),
                'href' => tenant_path('withdrawals'),
            ];
        }

        if ($approvable instanceof QardHasanFinancing) {
            $member = $approvable->member;
            $productName = $approvable->product?->name;
            $structure = $approvable->product?->structure?->label() ?? 'Qard Hasan';

            return [
                ...$empty,
                'category' => 'loan',
                'type' => 'qard_hasan',
                'type_label' => $productName ?: $structure,
                'amount' => money((int) $approvable->principal_kobo),
                'amount_label' => 'Principal',
                'member' => $member?->fullName(),
                'member_number' => $member?->membership_number,
                'member_href' => $member ? tenant_path('members/'.$member->uuid) : null,
                'branch' => $member?->branch?->name,
                'product' => $productName ?: $structure,
                'purpose' => $approvable->purpose,
                'meta' => array_values(array_filter([
                    $productName && $productName !== $structure ? $structure : null,
                    $approvable->tenure_months ? $approvable->tenure_months.' mo' : null,
                    $approvable->admin_fee_kobo ? 'Fee '.money((int) $approvable->admin_fee_kobo) : null,
                ])),
                'href' => tenant_path('loan'),
            ];
        }

        if ($approvable instanceof MurabahaFinancing) {
            $member = $approvable->member;
            $productName = $approvable->product?->name;
            $structure = $approvable->product?->structure?->label() ?? 'Murabaha';

            return [
                ...$empty,
                'category' => 'loan',
                'type' => 'murabaha',
                'type_label' => $productName ?: $structure,
                'amount' => money((int) $approvable->sale_price_kobo),
                'amount_label' => 'Sale price',
                'member' => $member?->fullName(),
                'member_number' => $member?->membership_number,
                'member_href' => $member ? tenant_path('members/'.$member->uuid) : null,
                'branch' => $member?->branch?->name,
                'product' => $productName ?: $structure,
                'purpose' => $approvable->asset_description,
                'meta' => array_values(array_filter([
                    $productName && $productName !== $structure ? $structure : null,
                    $approvable->supplier ? 'Supplier '.$approvable->supplier : null,
                    $approvable->purchase_cost_kobo ? 'Cost '.money((int) $approvable->purchase_cost_kobo) : null,
                    $approvable->profit_kobo ? 'Profit '.money((int) $approvable->profit_kobo) : null,
                    $approvable->down_payment_kobo ? 'Down '.money((int) $approvable->down_payment_kobo) : null,
                    $approvable->tenure_months ? $approvable->tenure_months.' mo' : null,
                ])),
                'href' => tenant_path('loan'),
            ];
        }

        return $empty;
    }

    private function workflowLabel(?string $name, ?string $appliesTo): string
    {
        if ($appliesTo === 'islamic_financing' || in_array($name, ['Islamic financing', 'Loans'], true)) {
            return 'Loan financing';
        }

        return $name ?: 'Approval';
    }
}
