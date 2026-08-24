<?php

namespace Tests\Feature;

use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Domain\Membership\MemberService;
use App\Enums\ApprovalStatus;
use App\Enums\CooperativeMode;
use App\Enums\CooperativeType;
use App\Enums\IslamicStructure;
use App\Enums\LoanStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\IslamicFinancingProduct;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\QardHasanFinancing;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_approvals_page_shows_member_amount_and_purpose(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);

        $member = Member::query()->create([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'membership_number' => 'M10001',
            'status' => 'active',
            'email' => 'ada.approval@example.com',
            'branch_id' => $this->headquartersId($tenant),
        ]);

        $product = LoanProduct::query()->create([
            'name' => 'Emergency loan',
            'minimum_amount_kobo' => 100000,
            'maximum_amount_kobo' => 50000000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'interest_method' => 'reducing_balance',
            'interest_rate_bps' => 1200,
            'processing_fee_kobo' => 0,
            'guarantors_required' => 0,
            'is_active' => true,
        ]);

        $application = LoanApplication::query()->create([
            'member_id' => $member->id,
            'loan_product_id' => $product->id,
            'amount_kobo' => 25000000,
            'tenure_months' => 6,
            'purpose' => 'School fees',
            'status' => LoanStatus::PendingApproval,
            'submitted_at' => now(),
        ]);

        $workflow = ApprovalWorkflow::query()->where('applies_to', 'loan')->first()
            ?? ApprovalWorkflow::query()->create([
                'name' => 'Loans',
                'applies_to' => 'loan',
                'min_amount_kobo' => 0,
                'is_active' => true,
            ]);

        if ($workflow->steps()->count() === 0) {
            $workflow->steps()->create([
                'position' => 1,
                'name' => 'Treasurer Approval',
                'role_name' => 'Administrator',
                'required_approvals' => 1,
            ]);
        }

        ApprovalRequest::query()->create([
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => LoanApplication::class,
            'approvable_id' => $application->id,
            'current_step' => 1,
            'status' => ApprovalStatus::Pending,
            'requested_by' => $admin->id,
        ]);

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/approvals")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Approvals/Index')
                ->has('requests', 1)
                ->where('requests.0.member', 'Ada Okeke')
                ->where('requests.0.member_number', 'M10001')
                ->where('requests.0.product', 'Emergency loan')
                ->where('requests.0.purpose', 'School fees')
                ->where('requests.0.type_label', 'Loan')
                ->where('requests.0.amount', money(25000000))
            );
    }

    public function test_reject_requires_a_comment(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);

        $member = Member::query()->create([
            'first_name' => 'Chidi',
            'last_name' => 'Bello',
            'membership_number' => 'M10002',
            'status' => 'active',
            'branch_id' => $this->headquartersId($tenant),
        ]);

        $loanProduct = LoanProduct::query()->create([
            'name' => 'Quick loan',
            'minimum_amount_kobo' => 100000,
            'maximum_amount_kobo' => 50000000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'interest_method' => 'flat',
            'interest_rate_bps' => 1000,
            'processing_fee_kobo' => 0,
            'guarantors_required' => 0,
            'is_active' => true,
        ]);

        $application = LoanApplication::query()->create([
            'member_id' => $member->id,
            'loan_product_id' => $loanProduct->id,
            'amount_kobo' => 1000000,
            'tenure_months' => 3,
            'purpose' => 'Working capital',
            'status' => LoanStatus::PendingApproval,
            'submitted_at' => now(),
        ]);

        $workflow = ApprovalWorkflow::query()->where('applies_to', 'loan')->first();
        $approval = ApprovalRequest::query()->create([
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => LoanApplication::class,
            'approvable_id' => $application->id,
            'current_step' => 1,
            'status' => ApprovalStatus::Pending,
            'requested_by' => $admin->id,
        ]);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/approvals")
            ->post("/{$tenant->slug}/approvals/{$approval->uuid}", [
                'decision' => 'rejected',
                'comment' => '',
            ])
            ->assertRedirect("/{$tenant->slug}/approvals")
            ->assertSessionHasErrors('comment');
    }

    public function test_mds_qard_hasan_lands_in_approvals_as_the_product_name(): void
    {
        [$tenant] = $this->makeCooperative([
            'type' => CooperativeType::Islamic->value,
            'mode' => CooperativeMode::Islamic->value,
        ]);
        $officer = $this->makeHqOfficer($tenant);

        $this->actingAs($officer);

        $member = app(MemberService::class)->register([
            'first_name' => 'Adaeze',
            'last_name' => 'Okonkwo',
        ]);
        app(MemberService::class)->approve($member);
        $this->fundMemberForLoan($member, 4_000_000);

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'MDS',
            'structure' => IslamicStructure::QardHasan,
            'minimum_amount_kobo' => 100000,
            'maximum_amount_kobo' => 50_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'admin_fee_kobo' => 0,
        ]);

        $financing = app(IslamicFinancingService::class)->createQardHasan(
            $member,
            $product,
            4_000_000,
            6,
            'Monthly deduction',
        );

        $this->assertSame(LoanStatus::PendingApproval, $financing->status);
        $this->assertDatabaseHas('approval_requests', [
            'approvable_type' => QardHasanFinancing::class,
            'approvable_id' => $financing->id,
            'status' => ApprovalStatus::Pending->value,
        ]);

        $this->get("/{$tenant->slug}/approvals")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Approvals/Index')
                ->has('requests', 1)
                ->where('requests.0.member', 'Adaeze Okonkwo')
                ->where('requests.0.type_label', 'MDS')
                ->where('requests.0.product', 'MDS')
                ->where('requests.0.amount', money(4_000_000))
            );
    }

    public function test_pending_mds_cannot_be_disbursed_until_approved(): void
    {
        [$tenant] = $this->makeCooperative([
            'type' => CooperativeType::Islamic->value,
            'mode' => CooperativeMode::Islamic->value,
        ]);
        $officer = $this->makeHqOfficer($tenant);

        $this->actingAs($officer);

        $member = app(MemberService::class)->register([
            'first_name' => 'Adaeze',
            'last_name' => 'Okonkwo',
        ]);
        app(MemberService::class)->approve($member);
        $this->fundMemberForLoan($member, 4_000_000);

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'MDS',
            'structure' => IslamicStructure::QardHasan,
            'minimum_amount_kobo' => 100000,
            'maximum_amount_kobo' => 50_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'admin_fee_kobo' => 0,
        ]);

        $financing = app(IslamicFinancingService::class)->createQardHasan($member, $product, 4_000_000, 6);

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/loan")
            ->post("/{$tenant->slug}/loan/qard/{$financing->uuid}/disburse")
            ->assertRedirect("/{$tenant->slug}/loan")
            ->assertSessionHas('error');

        $this->assertSame(LoanStatus::PendingApproval, $financing->fresh()->status);

        $approval = ApprovalRequest::query()
            ->where('approvable_type', QardHasanFinancing::class)
            ->where('approvable_id', $financing->id)
            ->firstOrFail();

        $steps = max(1, $approval->workflow->steps()->count());

        for ($i = 0; $i < $steps; $i++) {
            $this->from("/{$tenant->slug}/approvals")
                ->post("/{$tenant->slug}/approvals/{$approval->uuid}", [
                    'decision' => 'approved',
                ])
                ->assertRedirect("/{$tenant->slug}/approvals")
                ->assertSessionHas('success');
        }

        $this->assertSame(LoanStatus::Active, $financing->fresh()->status);
    }
}
