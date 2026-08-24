<?php

namespace Tests\Feature;

use App\Domain\Ledger\LedgerService;
use App\Domain\Membership\MemberService;
use App\Domain\Payments\WebhookProcessor;
use App\Domain\Savings\ContributionService;
use App\Enums\FinanceMode;
use App\Enums\MemberStatus;
use App\Models\ContributionProduct;
use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
use App\Models\Member;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_cannot_see_another_tenants_members(): void
    {
        [$alpha] = $this->makeCooperative(['name' => 'Alpha Cooperative']);
        $alphaMember = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada@example.com',
        ]);

        [$beta] = $this->makeCooperative(['name' => 'Beta Cooperative']);

        $this->assertSame(0, Member::query()->count());
        TenantContext::set($alpha);
        $this->assertTrue(Member::query()->whereKey($alphaMember->id)->exists());
        TenantContext::set($beta);
        $this->assertFalse(Member::query()->whereKey($alphaMember->id)->exists());
    }

    public function test_contribution_posts_a_balanced_journal_and_is_idempotent(): void
    {
        [$tenant] = $this->makeCooperative();
        $member = app(MemberService::class)->register([
            'first_name' => 'Tunde',
            'last_name' => 'Balogun',
        ]);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Monthly savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 2000000,
        ]);

        $service = app(ContributionService::class);
        $service->recordPayment($member, $product, 2000000, 'manual', 'pay:abc');
        $service->recordPayment($member, $product, 2000000, 'manual', 'pay:abc');

        $this->assertSame(1, FinancialTransaction::query()->count());

        $entry = JournalEntry::query()->first();
        $this->assertSame(
            $entry->lines()->sum('debit_kobo'),
            $entry->lines()->sum('credit_kobo'),
        );
        $this->assertSame(2000000, app(LedgerService::class)->memberSavingsBalance($member->fresh()));
    }

    public function test_paystack_webhook_replay_does_not_create_duplicates(): void
    {
        $payload = ['event' => 'charge.success', 'data' => ['reference' => 'PAY-123']];
        $processor = app(WebhookProcessor::class);

        $first = $processor->handle('manual', json_encode($payload), 'sig', $payload);
        $second = $processor->handle('manual', json_encode($payload), 'sig', $payload);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, \App\Models\PaymentWebhook::query()->count());
    }

    public function test_locked_savings_cannot_be_withdrawn(): void
    {
        $this->makeCooperative();
        $member = app(MemberService::class)->register(['first_name' => 'Ngozi', 'last_name' => 'Eze']);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Target savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'is_locked' => true,
            'ends_on' => now()->addYear()->toDateString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(ContributionService::class)->requestWithdrawal($member, $product, 100000);
    }

    public function test_inactive_member_cannot_be_registered_as_active_loan_applicant(): void
    {
        $this->makeCooperative();
        $member = app(MemberService::class)->register(['first_name' => 'Ife', 'last_name' => 'Dan']);
        $this->assertSame(MemberStatus::Pending, $member->status);

        $product = \App\Models\LoanProduct::query()->create([
            'name' => 'Emergency',
            'minimum_amount_kobo' => 1000000,
            'maximum_amount_kobo' => 5000000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 6,
            'interest_method' => 'flat',
            'interest_rate_bps' => 1000,
            'repayment_frequency' => 'monthly',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(\App\Domain\Lending\LoanService::class)->apply($member, $product, 1000000, 3);
    }
}
