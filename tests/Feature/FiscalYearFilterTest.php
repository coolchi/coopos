<?php

namespace Tests\Feature;

use App\Domain\Membership\MemberService;
use App\Enums\ContributionBucket;
use App\Enums\ContributionFrequency;
use App\Enums\FinanceMode;
use App\Models\Contribution;
use App\Models\ContributionProduct;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalYearFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_financial_year_filters_contribution_lists(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $tenant->settings->update([
            'fiscal_year_start_month' => 3,
            'fiscal_year_one_start_year' => 2025,
        ]);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
        ]);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Thrift',
            'bucket' => ContributionBucket::Savings,
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => ContributionFrequency::Monthly,
            'minimum_amount_kobo' => 100000,
            'is_active' => true,
        ]);

        Contribution::query()->create([
            'member_id' => $member->id,
            'contribution_product_id' => $product->id,
            'amount_kobo' => 100000,
            'paid_on' => '2025-06-15',
            'channel' => 'cash',
        ]);

        Contribution::query()->create([
            'member_id' => $member->id,
            'contribution_product_id' => $product->id,
            'amount_kobo' => 250000,
            'paid_on' => '2026-06-15',
            'channel' => 'cash',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-21'));

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/current-fiscal-year", ['year' => 2025])
            ->assertRedirect();

        $hq = $tenant->branches()->where('is_headquarters', true)->firstOrFail();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/contributions?tab=transactions&branch={$hq->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Savings/Contributions')
                ->has('contributions.data', 1)
                ->where('contributions.data.0.paid_on', '2025-06-15')
                ->where('financial_year.display', 'Year 1 2025/2026')
            );

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/current-fiscal-year", ['year' => 2026])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/contributions?tab=transactions&branch={$hq->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('contributions.data', 1)
                ->where('contributions.data.0.paid_on', '2026-06-15')
                ->where('financial_year.display', 'Year 2 2026/2027')
            );

        Carbon::setTestNow();
    }

    public function test_fiscal_year_options_span_from_year_one_to_current(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $tenant->settings->update([
            'fiscal_year_start_month' => 3,
            'fiscal_year_one_start_year' => 2025,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-21'));

        $this->actingAs($admin)
            ->withSession(['fiscal_year_start_year' => 2026])
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('financial_year.year', 2026)
                ->has('financial_year.options', 2)
                ->where('financial_year.options.0.year', 2026)
                ->where('financial_year.options.1.year', 2025)
            );

        Carbon::setTestNow();
    }
}
