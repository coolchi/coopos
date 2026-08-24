<?php

namespace Tests\Feature;

use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Domain\Lending\LoanService;
use App\Domain\Membership\MemberService;
use App\Enums\IslamicStructure;
use App\Models\IslamicFinancingProduct;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancingRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarantor_exposure_cap_is_enforced(): void
    {
        [$tenant] = $this->makeCooperative();
        $tenant->settings->update(['guarantor_exposure_cap_kobo' => 5_000_000]);

        $borrower = app(MemberService::class)->register(['first_name' => 'A', 'last_name' => 'Borrower']);
        $guarantor = app(MemberService::class)->register(['first_name' => 'B', 'last_name' => 'Guarantor']);
        app(MemberService::class)->approve($borrower);
        app(MemberService::class)->approve($guarantor);
        $this->fundMemberForLoan($borrower, 1_000_000);

        $product = LoanProduct::query()->create([
            'name' => 'Personal',
            'minimum_amount_kobo' => 1_000_000,
            'maximum_amount_kobo' => 20_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'interest_method' => 'flat',
            'interest_rate_bps' => 1000,
            'guarantors_required' => 1,
            'repayment_frequency' => 'monthly',
        ]);

        $application = app(LoanService::class)->apply($borrower, $product, 1_000_000, 3, 'Test');
        LoanGuarantor::query()->where('loan_application_id', $application->id)->update(['status' => 'accepted']);

        $this->expectException(\InvalidArgumentException::class);
        app(LoanService::class)->addGuarantor($application, $guarantor, 6_000_000);
    }

    public function test_qard_hasan_disbursement_never_creates_interest(): void
    {
        $this->makeCooperative(['mode' => 'islamic']);
        $member = app(MemberService::class)->register(['first_name' => 'Yusuf', 'last_name' => 'Bello']);
        app(MemberService::class)->approve($member);
        $this->fundMemberForLoan($member, 1_000_000);

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'Qard',
            'structure' => IslamicStructure::QardHasan,
            'minimum_amount_kobo' => 1000000,
            'maximum_amount_kobo' => 5000000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'admin_fee_kobo' => 50000,
        ]);

        $financing = app(IslamicFinancingService::class)->createQardHasan($member, $product, 1000000, 4);
        $this->assertSame(1050000, $financing->total_repayable_kobo);
        $this->assertSame($financing->principal_kobo + $financing->admin_fee_kobo, $financing->total_repayable_kobo);
        $this->assertFalse(isset($financing->interest_kobo));
    }

    public function test_murabaha_preserves_disclosed_profit(): void
    {
        $this->makeCooperative(['mode' => 'islamic']);
        $member = app(MemberService::class)->register(['first_name' => 'Fatima', 'last_name' => 'Sani']);
        app(MemberService::class)->approve($member);
        $this->fundMemberForLoan($member, 20_000_000);

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'Murabaha',
            'structure' => IslamicStructure::Murabaha,
            'minimum_amount_kobo' => 1000000,
            'maximum_amount_kobo' => 50_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
        ]);

        $financing = app(IslamicFinancingService::class)->createMurabaha($member, $product, [
            'asset_description' => 'Fridge',
            'purchase_cost_kobo' => 20000000,
            'profit_kobo' => 2000000,
            'down_payment_kobo' => 0,
            'tenure_months' => 5,
        ]);

        $this->assertSame(22000000, $financing->sale_price_kobo);
        $this->assertSame($financing->purchase_cost_kobo + $financing->profit_kobo, $financing->sale_price_kobo);
    }

    public function test_portal_qard_application_outside_limits_returns_validation_error(): void
    {
        [$tenant] = $this->makeCooperative(['mode' => 'islamic']);
        $member = app(MemberService::class)->register([
            'first_name' => 'Yusuf',
            'last_name' => 'Bello',
            'email' => 'yusuf.bello@example.com',
            'password' => 'Password1',
        ]);
        app(MemberService::class)->approve($member);

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'Qard',
            'structure' => IslamicStructure::QardHasan,
            'minimum_amount_kobo' => 2_000_000,
            'maximum_amount_kobo' => 30_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'admin_fee_kobo' => 50000,
        ]);

        $this->actingAs($member->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/portal")
            ->post("/{$tenant->slug}/portal/apply/qard", [
                'product_uuid' => $product->uuid,
                'amount' => 5000,
                'tenure_months' => 6,
                'purpose' => 'Welfare',
            ])
            ->assertRedirect("/{$tenant->slug}/portal")
            ->assertSessionHasErrors('amount');
    }

    public function test_staff_murabaha_over_product_limit_returns_validation_error(): void
    {
        [$tenant] = $this->makeCooperative(['mode' => 'islamic']);
        $officer = $this->makeHqOfficer($tenant);
        $member = app(MemberService::class)->register(['first_name' => 'Fatima', 'last_name' => 'Sani']);
        app(MemberService::class)->approve($member);

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'Murabaha',
            'structure' => IslamicStructure::Murabaha,
            'minimum_amount_kobo' => 1_000_000,
            'maximum_amount_kobo' => 10_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
        ]);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/loan")
            ->post("/{$tenant->slug}/loan/murabaha", [
                'member_uuid' => $member->uuid,
                'product_uuid' => $product->uuid,
                'asset_description' => 'Fridge',
                'purchase_cost' => 150000,
                'profit' => 10000,
                'down_payment' => 0,
                'tenure_months' => 6,
            ])
            ->assertRedirect("/{$tenant->slug}/loan")
            ->assertSessionHasErrors('purchase_cost');
    }
}
