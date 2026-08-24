<?php

namespace Tests\Feature;

use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Domain\Lending\LoanService;
use App\Domain\Membership\MemberService;
use App\Enums\ContributionBucket;
use App\Enums\FinanceMode;
use App\Enums\IslamicStructure;
use App\Enums\LoanStatus;
use App\Models\Branch;
use App\Models\Contribution;
use App\Models\ContributionProduct;
use App\Models\FinancialTransaction;
use App\Models\IslamicFinancingProduct;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Member;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributions_page_includes_mds_and_outstanding_loan_lines(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $member = $this->approvedMember();
        $withoutLoan = $this->approvedMember('Chidi', 'Okafor', 'chidi.mds@example.com');
        $loan = $this->disburseLoan($member);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/contributions")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Savings/Contributions')
                ->has('required_products')
                ->missing("member_facilities.{$withoutLoan->uuid}.0")
                ->has("member_facilities.{$member->uuid}", 1)
                ->where("member_facilities.{$member->uuid}.0.kind", 'loan')
                ->where("member_facilities.{$member->uuid}.0.uuid", $loan->uuid)
                ->where("member_facilities.{$member->uuid}.0.is_mds", false)
                ->has('columns')
            );

        $mds = ContributionProduct::query()->where('bucket', ContributionBucket::Mds)->first();
        $this->assertNotNull($mds);
        $this->assertFalse($mds->is_required);
    }

    public function test_mds_named_loan_is_flagged_on_the_payment_catalog(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $member = $this->approvedMember('Yusuf', 'Bello', 'yusuf.mdsloan@example.com');
        $loan = $this->disburseLoan($member, 'MDS');

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/contributions")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where("member_facilities.{$member->uuid}.0.uuid", $loan->uuid)
                ->where("member_facilities.{$member->uuid}.0.is_mds", true)
            );
    }

    public function test_staff_can_record_mds_and_loan_repayment_from_contributions(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $csrf = PreventRequestForgery::class;
        $member = $this->approvedMember();
        $loan = $this->disburseLoan($member);

        $this->actingAs($officer)->get("/{$tenant->slug}/contributions")->assertOk();

        $mds = ContributionProduct::query()->where('bucket', ContributionBucket::Mds)->firstOrFail();
        $due = $loan->repayments()->orderBy('installment_number')->firstOrFail();
        $amount = $due->outstandingKobo() / 100;
        $outstandingBefore = $loan->outstanding_kobo;

        $this->actingAs($officer)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/contributions")
            ->post("/{$tenant->slug}/contributions", [
                'member_uuid' => $member->uuid,
                'for_month' => now()->format('Y-m'),
                'paid_on' => now()->toDateString(),
                'payments' => [
                    [
                        'kind' => 'product',
                        'product_uuid' => $mds->uuid,
                        'amount' => 5000,
                        'channel' => 'cash',
                    ],
                    [
                        'kind' => 'loan',
                        'facility_uuid' => $loan->uuid,
                        'amount' => $amount,
                        'channel' => 'cash',
                    ],
                ],
            ])
            ->assertRedirect("/{$tenant->slug}/contributions")
            ->assertSessionHas('success');

        $this->assertSame(500000, Contribution::query()->where('contribution_product_id', $mds->id)->value('amount_kobo'));
        $this->assertSame(
            $outstandingBefore - $due->outstandingKobo(),
            $loan->fresh()->outstanding_kobo
        );
        $this->assertSame('paid', $due->fresh()->status);
        $this->assertSame(1, FinancialTransaction::query()->where('type', 'loan_repayment')->count());

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/contributions?tab=transactions")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('contributions.data', 2));
    }

    public function test_qard_repayment_from_loans_page_appears_on_contributions(): void
    {
        [$tenant] = $this->makeCooperative(['mode' => 'islamic']);
        $officer = $this->makeHqOfficer($tenant);
        $csrf = PreventRequestForgery::class;
        $member = $this->approvedMember('Yusuf', 'Bello', 'yusuf.repay@example.com');
        $this->fundMemberForLoan($member, 1_000_000);

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'MDS',
            'structure' => IslamicStructure::QardHasan,
            'minimum_amount_kobo' => 1_000_000,
            'maximum_amount_kobo' => 5_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'admin_fee_kobo' => 0,
        ]);

        $service = app(IslamicFinancingService::class);
        $financing = $service->createQardHasan($member, $product, 1_000_000, 4, 'Welfare');
        $financing->update(['status' => LoanStatus::Approved]);
        $financing = $service->disburseQardHasan($financing->fresh());
        $due = $financing->installments()->orderBy('installment_number')->firstOrFail();
        $amount = $due->outstandingKobo() / 100;

        $this->actingAs($officer)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/loan")
            ->post("/{$tenant->slug}/loan/qard/{$financing->uuid}/repay", [
                'amount' => $amount,
                'channel' => 'transfer',
                'paid_on' => now()->toDateString(),
            ])
            ->assertRedirect("/{$tenant->slug}/loan")
            ->assertSessionHas('success');

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/contributions?tab=transactions")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('contributions.data', 1)
                ->where('contributions.data.0.kind', 'qard_hasan_repayment')
                ->where('contributions.data.0.editable', false)
                ->where('contributions.data.0.channel', 'transfer')
            );

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/loan")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('qard.0.transaction_count', 1)
                ->has('qard.0.repayments', 1)
                ->where('qard.0.repayments.0.channel', 'Transfer')
                ->where('qard.0.repayments.0.product', 'MDS')
                ->where('qard.0.account', $financing->account_number)
                ->has('qard.0.disbursed_on')
                ->has('qard.0.maturity_on')
            );
    }

    public function test_legacy_product_only_payload_still_records_a_contribution(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $member = $this->approvedMember();
        $product = ContributionProduct::query()->create([
            'name' => 'Monthly savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 500000,
            'is_active' => true,
        ]);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/members")
            ->post("/{$tenant->slug}/contributions", [
                'member_uuid' => $member->uuid,
                'payments' => [
                    ['product_uuid' => $product->uuid, 'amount' => 5000, 'channel' => 'cash'],
                ],
            ])
            ->assertRedirect("/{$tenant->slug}/members");

        $this->assertSame(1, Contribution::query()->where('member_id', $member->id)->count());
    }

    public function test_tenant_admin_must_select_a_branch_to_see_contribution_records(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $this->approvedMember();
        $ikeja = Branch::query()->create([
            'name' => 'Ikeja',
            'code' => 'IKEJA',
            'city' => 'Ikeja',
            'state' => 'Lagos',
        ]);
        $ikejaMember = $this->approvedMember('Ikeja', 'Bola', 'ikeja.contrib@example.com');
        $ikejaMember->update(['branch_id' => $ikeja->id]);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/contributions")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Savings/Contributions')
                ->where('needs_branch', true)
                ->has('rows', 0)
                ->has('branches', 2)
            );

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/contributions?branch={$ikeja->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('needs_branch', false)
                ->has('rows', 1)
                ->where('rows.0.name', 'Ikeja Bola')
            );

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/contributions/enrollments")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Savings/Enrollments')
                ->where('needs_branch', true)
                ->has('rows', 0)
            );

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/contributions/enrollments?branch={$ikeja->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('needs_branch', false)
            );
    }

    private function approvedMember(string $first = 'Ada', string $last = 'Okeke', string $email = 'ada.mds@example.com'): Member
    {
        $member = app(MemberService::class)->register([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
        ]);
        app(MemberService::class)->approve($member);

        return $member->fresh();
    }

    private function disburseLoan(Member $member, string $productName = 'Emergency'): Loan
    {
        $product = LoanProduct::query()->create([
            'name' => $productName,
            'minimum_amount_kobo' => 1_000_000,
            'maximum_amount_kobo' => 5_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'interest_method' => 'flat',
            'interest_rate_bps' => 1000,
            'repayment_frequency' => 'monthly',
        ]);

        $this->fundMemberForLoan($member, 1_000_000);

        $application = app(LoanService::class)->apply($member, $product, 1_000_000, 3, 'Test');

        return app(LoanService::class)->disburse($application);
    }
}
