<?php

namespace Tests\Unit;

use App\Domain\IslamicFinance\MurabahaCalculator;
use App\Domain\IslamicFinance\QardHasanCalculator;
use App\Domain\Lending\InterestCalculator;
use App\Enums\InterestMethod;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class FinanceCalculatorsTest extends TestCase
{
    public function test_money_formats_naira_from_kobo(): void
    {
        $this->assertSame('₦20,000', (string) Money::of(2_000_000));
        $this->assertSame('₦123.45', (string) Money::of(12_345));
        $this->assertSame(250000, Money::fromNaira(2500)->kobo());
    }

    public function test_qard_hasan_total_is_principal_plus_fee_only(): void
    {
        $result = (new QardHasanCalculator)->schedule(100_000_00, 1_000_00, 4, '2026-01-01');

        $this->assertSame(101_000_00, $result['total_repayable_kobo']);
        $this->assertSame(101_000_00, collect($result['schedule'])->sum('amount_due_kobo'));
        $this->assertArrayNotHasKey('interest_kobo', $result);
    }

    public function test_murabaha_sale_price_is_cost_plus_profit(): void
    {
        $result = (new MurabahaCalculator)->schedule(200_000_00, 20_000_00, 20_000_00, 5, '2026-01-01');

        $this->assertSame(220_000_00, $result['sale_price_kobo']);
        $this->assertSame(200_000_00, $result['financed_kobo']);
        $this->assertSame(200_000_00, collect($result['schedule'])->sum('amount_due_kobo'));
    }

    public function test_flat_interest_schedule_balances(): void
    {
        $result = (new InterestCalculator)->schedule(120_000_00, 1200, 12, InterestMethod::Flat, '2026-01-01');

        $this->assertSame(collect($result['schedule'])->sum('amount_due_kobo'), $result['total_repayable_kobo']);
        $this->assertGreaterThan(0, $result['interest_kobo']);
    }
}
