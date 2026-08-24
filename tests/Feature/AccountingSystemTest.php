<?php

namespace Tests\Feature;

use App\Domain\Ledger\AccountingReportService;
use App\Domain\Ledger\AccountingService;
use App\Domain\Membership\MemberService;
use App\Domain\Savings\ContributionService;
use App\Enums\AccountType;
use App\Enums\ContributionBucket;
use App\Enums\FinanceMode;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\AccountingVoucher;
use App\Models\ContributionProduct;
use App\Models\JournalEntry;
use App\Support\FiscalPeriod;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_of_accounts_is_provisioned_with_pl_heads(): void
    {
        $this->makeCooperative();

        $this->assertTrue(Account::query()->where('code', '4100')->where('name', 'Registration Form')->exists());
        $this->assertTrue(Account::query()->where('code', '5010')->where('type', AccountType::Expense)->exists());
        $this->assertTrue(Account::query()->where('code', '3200')->exists());
        $this->assertGreaterThan(20, Account::query()->count());
    }

    public function test_staff_can_open_the_chart_and_record_an_expense(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $rent = Account::query()->where('code', '5010')->firstOrFail();
        $bank = Account::query()->where('code', '1010')->firstOrFail();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/accounts")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Accounting/Chart')
                ->has('accounts')
                ->where('counts.expense', fn ($count) => $count >= 1)
            );

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/accounts/expenses", [
                'account_uuid' => $rent->uuid,
                'contra_account_uuid' => $bank->uuid,
                'amount' => 25000,
                'occurred_on' => now()->toDateString(),
                'payee' => 'Landlord',
                'description' => 'August rent',
            ])
            ->assertRedirect();

        $this->assertSame(1, AccountingVoucher::query()->where('type', VoucherType::Expense)->count());
        $voucher = AccountingVoucher::query()->first();
        $this->assertMatchesRegularExpression('/^EXP-\d{5}$/', (string) $voucher->reference);
        $entry = JournalEntry::query()->first();
        $this->assertNotNull($entry);
        $this->assertSame(
            $entry->lines()->sum('debit_kobo'),
            $entry->lines()->sum('credit_kobo'),
        );
        $this->assertSame(2500000, (int) $entry->lines()->where('account_id', $rent->id)->sum('debit_kobo'));

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/accounts/expenses")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Accounting/Vouchers')
                ->where('vouchers.data.0.reference', $voucher->reference)
                ->where('vouchers.data.0.occurred_on', now()->format('d/m/Y'))
                ->where('vouchers.data.0.payee', 'Landlord')
            );

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/accounts/expenses?q=Landlord")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('vouchers.data', 1));

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/accounts/expenses?q=no-such-payee")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('vouchers.data', 0));
    }

    public function test_profit_and_loss_splits_surplus_sixty_twenty_twenty(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $accounting = app(AccountingService::class);
        $donations = Account::query()->where('code', '4170')->firstOrFail();
        $rent = Account::query()->where('code', '5010')->firstOrFail();
        $bank = Account::query()->where('code', '1010')->firstOrFail();

        $this->actingAs($admin);
        $accounting->recordVoucher([
            'type' => VoucherType::Income->value,
            'account_uuid' => $donations->uuid,
            'contra_account_uuid' => $bank->uuid,
            'amount' => 100000,
            'occurred_on' => now()->toDateString(),
            'description' => 'Member donation',
        ]);
        $accounting->recordVoucher([
            'type' => VoucherType::Expense->value,
            'account_uuid' => $rent->uuid,
            'contra_account_uuid' => $bank->uuid,
            'amount' => 40000,
            'occurred_on' => now()->toDateString(),
            'description' => 'Hall rent',
        ]);

        $statement = app(AccountingReportService::class)->profitAndLoss(FiscalPeriod::forYear());

        $this->assertSame(6000000, $statement['net_surplus_kobo']);
        $this->assertSame(3600000, $statement['allocation']['profit_share_kobo']);
        $this->assertSame(1200000, $statement['allocation']['honorarium_kobo']);
        $this->assertSame(1200000, $statement['allocation']['reserve_fund_kobo']);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/reports/profit-loss")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Reports/ProfitLoss')
                ->where('net_surplus_kobo', 6000000)
                ->has('income')
                ->has('expenses')
                ->has('allocation.profit_share')
            );
    }

    public function test_system_accounts_cannot_be_deleted(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $cash = Account::query()->where('code', '1000')->firstOrFail();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->delete("/{$tenant->slug}/accounts/{$cash->uuid}")
            ->assertRedirect();

        $this->assertTrue(Account::query()->whereKey($cash->id)->exists());
    }

    public function test_staff_can_add_a_custom_expense_account(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/accounts", [
                'code' => '5190',
                'name' => 'Security',
                'type' => AccountType::Expense->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('accounts', [
            'code' => '5190',
            'name' => 'Security',
            'is_system' => false,
        ]);
    }

    public function test_trial_balance_stays_in_balance_after_vouchers(): void
    {
        $admin = $this->makeCooperative()[1];
        $this->actingAs($admin);

        $fines = Account::query()->where('code', '4030')->firstOrFail();
        $salary = Account::query()->where('code', '5030')->firstOrFail();
        $cash = Account::query()->where('code', '1000')->firstOrFail();
        $accounting = app(AccountingService::class);

        $accounting->recordVoucher([
            'type' => VoucherType::Income->value,
            'account_uuid' => $fines->uuid,
            'contra_account_uuid' => $cash->uuid,
            'amount' => 5000,
            'occurred_on' => now()->toDateString(),
            'description' => 'Lateness fine',
        ]);
        $accounting->recordVoucher([
            'type' => VoucherType::Expense->value,
            'account_uuid' => $salary->uuid,
            'contra_account_uuid' => $cash->uuid,
            'amount' => 1500,
            'occurred_on' => now()->toDateString(),
            'description' => 'Clerk salary',
        ]);

        $tb = app(AccountingReportService::class)->trialBalance();
        $this->assertTrue($tb['is_balanced']);
        $this->assertSame($tb['debit_total'], $tb['credit_total']);
    }

    public function test_registration_fee_credits_income_not_savings(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $member = app(MemberService::class)->register([
            'first_name' => 'Bisi',
            'last_name' => 'Ade',
        ]);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Registration form',
            'bucket' => ContributionBucket::RegistrationFee,
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
        ]);

        $this->actingAs($admin);
        app(ContributionService::class)->recordPayment($member, $product, 100000, 'cash');

        $income = Account::query()->where('code', '4100')->firstOrFail();
        $this->assertSame(100000, (int) $income->lines()->sum('credit_kobo'));
    }
}
