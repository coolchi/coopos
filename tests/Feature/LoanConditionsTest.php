<?php

namespace Tests\Feature;

use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Lending\LoanEligibilityService;
use App\Domain\Lending\LoanService;
use App\Domain\Membership\MemberService;
use App\Domain\Savings\ContributionService;
use App\Enums\ContributionBucket;
use App\Enums\ContributionFrequency;
use App\Enums\FinanceMode;
use App\Enums\IslamicStructure;
use App\Models\ContributionProduct;
use App\Models\IslamicFinancingProduct;
use App\Models\LoanProduct;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanConditionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_preview_loan_eligibility_checks(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $member = $this->member();

        $this->actingAs($admin)
            ->getJson("/{$tenant->slug}/loans/eligibility?member_uuid={$member->uuid}&amount=10000")
            ->assertOk()
            ->assertJsonPath('passed', false)
            ->assertJsonFragment(['id' => 'savings'])
            ->assertJsonFragment(['id' => 'shares'])
            ->assertJsonFragment(['id' => 'attendance']);
    }

    public function test_member_without_required_savings_cannot_apply(): void
    {
        $this->makeCooperative();
        $member = $this->member();
        $this->fundMemberForLoan($member, 1_000_000);
        $ledger = app(LedgerService::class);
        $ledger->recordTransaction(
            type: 'opening_balance',
            amountKobo: 400_000,
            journalLines: [
                ['account' => '2100', 'debit' => 400_000, 'member' => $member, 'memo' => 'Reduce savings'],
                ['account' => '1010', 'credit' => 400_000, 'memo' => 'Reduce savings'],
            ],
            member: $member,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('savings');
        app(LoanService::class)->apply($member, $this->loanProduct(), 1_000_000, 3);
    }

    public function test_member_without_required_shares_cannot_apply(): void
    {
        $this->makeCooperative();
        $member = $this->member();
        $this->fundMemberForLoan($member, 1_000_000);
        $ledger = app(LedgerService::class);
        $ledger->recordTransaction(
            type: 'opening_balance',
            amountKobo: 50_000,
            journalLines: [
                ['account' => '3000', 'debit' => 50_000, 'member' => $member, 'memo' => 'Reduce shares'],
                ['account' => '1010', 'credit' => 50_000, 'memo' => 'Reduce shares'],
            ],
            member: $member,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('shares');
        app(LoanService::class)->apply($member, $this->loanProduct(), 1_000_000, 3);
    }

    public function test_unpaid_agm_blocks_a_loan(): void
    {
        $this->makeCooperative();
        $member = $this->member();
        $this->fundMemberForLoan($member, 1_000_000);
        $this->feeProduct(ContributionBucket::Agm, ContributionFrequency::Yearly, 'AGM');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AGM');
        app(LoanService::class)->apply($member, $this->loanProduct(), 1_000_000, 3);
    }

    public function test_paid_agm_allows_a_loan(): void
    {
        $this->makeCooperative();
        $member = $this->member();
        $this->fundMemberForLoan($member, 1_000_000);
        $agm = $this->feeProduct(ContributionBucket::Agm, ContributionFrequency::Yearly, 'AGM');
        app(ContributionService::class)->recordPayment($member, $agm, 10_000, 'manual', 'test-agm:'.$member->uuid, skipLimits: true);

        $application = app(LoanService::class)->apply($member, $this->loanProduct(), 1_000_000, 3);
        $this->assertNotNull($application->id);
        $this->assertTrue($application->eligibility_snapshot['passed']);
    }

    public function test_three_absences_in_a_year_block_a_loan(): void
    {
        $this->makeCooperative();
        $member = $this->member();
        $this->fundMemberForLoan($member, 1_000_000);

        foreach (range(1, 3) as $i) {
            $meeting = Meeting::query()->create([
                'title' => "Meeting {$i}",
                'held_on' => now()->subDays($i)->toDateString(),
                'kind' => 'general',
            ]);
            MeetingAttendance::query()->create([
                'meeting_id' => $meeting->id,
                'member_id' => $member->id,
                'present' => false,
                'status' => 'absent',
            ]);
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('absences');
        app(LoanService::class)->apply($member, $this->loanProduct(), 1_000_000, 3);
    }

    public function test_permitted_absence_does_not_count_toward_the_limit(): void
    {
        $this->makeCooperative();
        $member = $this->member();
        $this->fundMemberForLoan($member, 1_000_000);

        foreach (['absent', 'absent', 'permitted'] as $i => $status) {
            $meeting = Meeting::query()->create([
                'title' => "Meeting {$i}",
                'held_on' => now()->subDays($i + 1)->toDateString(),
                'kind' => 'general',
            ]);
            MeetingAttendance::query()->create([
                'meeting_id' => $meeting->id,
                'member_id' => $member->id,
                'present' => $status === 'present',
                'status' => $status,
            ]);
        }

        $result = app(LoanEligibilityService::class)->evaluate($member, 1_000_000);
        $attendance = collect($result['checks'])->firstWhere('id', 'attendance');
        $this->assertTrue($attendance['pass']);
        $this->assertSame('2', $attendance['actual']);
    }

    public function test_islamic_qard_uses_the_same_savings_rule(): void
    {
        $this->makeCooperative(['mode' => 'islamic']);
        $member = $this->member();

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'Qard',
            'structure' => IslamicStructure::QardHasan,
            'minimum_amount_kobo' => 1_000_000,
            'maximum_amount_kobo' => 5_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'admin_fee_kobo' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('savings');
        app(IslamicFinancingService::class)->createQardHasan($member, $product, 1_000_000, 4);
    }

    public function test_turning_off_a_required_product_in_settings_skips_that_check(): void
    {
        [$tenant] = $this->makeCooperative();
        $member = $this->member();
        $this->fundMemberForLoan($member, 1_000_000);
        $this->feeProduct(ContributionBucket::Agm, ContributionFrequency::Yearly, 'AGM');
        tenant()->settings->update(['loan_required_product_uuids' => []]);

        $result = app(LoanEligibilityService::class)->evaluate($member, 1_000_000);
        $this->assertTrue($result['passed']);
        $this->assertFalse(
            collect($result['checks'])->contains(fn ($check) => str_contains((string) ($check['label'] ?? ''), 'AGM'))
        );
    }

    public function test_a_custom_product_can_be_required_for_a_loan(): void
    {
        [$tenant] = $this->makeCooperative();
        $member = $this->member();
        $this->fundMemberForLoan($member, 1_000_000);
        $levy = $this->feeProduct(ContributionBucket::Other, ContributionFrequency::Yearly, 'Building levy');
        tenant()->settings->update(['loan_required_product_uuids' => [$levy->uuid]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Building levy');
        app(LoanService::class)->apply($member, $this->loanProduct(), 1_000_000, 3);
    }

    public function test_policies_page_lists_this_cooperatives_products(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $this->feeProduct(ContributionBucket::Other, ContributionFrequency::Yearly, 'Building levy');

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/settings/policies")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Settings/Policies')
                ->has('contribution_products')
                ->where('contribution_products', fn ($products) => collect($products)->contains('name', 'Building levy'))
            );
    }

    private function member(): Member
    {
        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.loan@example.com',
        ]);
        app(MemberService::class)->approve($member);

        return $member->fresh();
    }

    private function loanProduct(): LoanProduct
    {
        return LoanProduct::query()->create([
            'name' => 'Emergency',
            'minimum_amount_kobo' => 1_000_000,
            'maximum_amount_kobo' => 5_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'interest_method' => 'flat',
            'interest_rate_bps' => 1000,
            'repayment_frequency' => 'monthly',
        ]);
    }

    private function feeProduct(ContributionBucket $bucket, ContributionFrequency $frequency, string $name): ContributionProduct
    {
        return ContributionProduct::query()->create([
            'name' => $name,
            'bucket' => $bucket,
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => $frequency,
            'minimum_amount_kobo' => 0,
            'default_amount_kobo' => 10_000,
            'is_active' => true,
            'is_required' => true,
        ]);
    }
}
