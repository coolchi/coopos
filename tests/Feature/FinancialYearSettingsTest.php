<?php

namespace Tests\Feature;

use App\Support\FiscalPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialYearSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_march_to_february_financial_year_labels_and_year_number(): void
    {
        [$tenant] = $this->makeCooperative();

        $tenant->settings->update([
            'fiscal_year_start_month' => 3,
            'fiscal_year_one_start_year' => 2025,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-21'));

        $period = FiscalPeriod::forYear();

        $this->assertSame(3, FiscalPeriod::startMonth());
        $this->assertSame(2, FiscalPeriod::endMonth());
        $this->assertSame('2026/2027', $period->label());
        $this->assertSame(2, $period->yearNumber());
        $this->assertSame('Year 2 2026/2027', $period->displayLabel());
        $this->assertSame('March – February', $period->monthsLabel());

        Carbon::setTestNow(Carbon::parse('2026-02-15'));
        $prior = FiscalPeriod::forYear();
        $this->assertSame('Year 1 2025/2026', $prior->displayLabel());

        Carbon::setTestNow();
    }

    public function test_organization_settings_save_financial_year_and_share_on_dashboard(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings", [
                'name' => $tenant->name,
                'membership_number_prefix' => 'M',
                'membership_fee' => 0,
                'currency' => 'NGN',
                'fiscal_year_start_month' => 3,
                'fiscal_year_one_start_year' => 2025,
            ])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertSame(3, (int) $tenant->settings->fiscal_year_start_month);
        $this->assertSame(2025, (int) $tenant->settings->fiscal_year_one_start_year);

        Carbon::setTestNow(Carbon::parse('2026-02-15'));

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('financial_year.display', 'Year 1 2025/2026')
                ->where('financial_year.months_label', 'March – February')
                ->where('financial_year.start_month', 3)
            );

        Carbon::setTestNow();
    }
}
