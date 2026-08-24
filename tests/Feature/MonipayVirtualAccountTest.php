<?php

namespace Tests\Feature;

use App\Domain\Ledger\WalletService;
use App\Domain\Membership\MemberService;
use App\Domain\Payments\WebhookProcessor;
use App\Enums\ContributionBucket;
use App\Enums\FinanceMode;
use App\Models\Contribution;
use App\Models\ContributionProduct;
use App\Models\FinancialTransaction;
use App\Models\MemberVirtualAccount;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonipayVirtualAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_issue_a_monipay_virtual_account(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        config(['services.monipay.secret' => 'sk_test_coopos']);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.va@example.com',
        ]);
        app(MemberService::class)->approve($member);

        Http::fake([
            'api.monipay.ng/customer-wallet' => Http::response([
                'status' => true,
                'message' => 'Customer wallet created successfully',
                'data' => [
                    'wallet_id' => 'WTEST1',
                    'customer_id' => 'CUSTEST1',
                    'customer_email' => 'ada.va@example.com',
                    'status' => 'APPROVED',
                    'account_name' => 'ADA OKEKE',
                    'account_number' => '1111234567',
                    'bank_name' => 'Monipay MFB',
                    'reference' => 'MEM-'.$member->membership_number,
                    'virtual_accounts' => [],
                ],
            ], 200),
        ]);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/members/{$member->uuid}")
            ->post("/{$tenant->slug}/members/{$member->uuid}/virtual-account")
            ->assertRedirect();

        $this->assertDatabaseHas('member_virtual_accounts', [
            'member_id' => $member->id,
            'provider' => 'monipay',
            'account_number' => '1111234567',
            'wallet_id' => 'WTEST1',
        ]);
    }

    public function test_monipay_charge_credits_the_member_wallet(): void
    {
        [$tenant] = $this->makeCooperative();
        config(['services.monipay.secret' => 'sk_test_coopos']);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.credit@example.com',
        ]);
        app(MemberService::class)->approve($member);

        ContributionProduct::query()->create([
            'name' => 'Savings',
            'bucket' => ContributionBucket::Savings,
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 0,
            'is_active' => true,
        ]);

        MemberVirtualAccount::query()->create([
            'member_id' => $member->id,
            'provider' => 'monipay',
            'wallet_id' => 'WTEST1',
            'account_number' => '1111234567',
            'account_name' => 'ADA OKEKE',
            'bank_name' => 'Monipay MFB',
            'status' => 'approved',
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'MPY-VA-1',
                'amount' => 750000,
                'account_number' => '1111234567',
                'wallet_id' => 'WTEST1',
            ],
        ];
        $raw = json_encode($payload);
        $signature = hash_hmac('sha512', $raw, 'sk_test_coopos');

        $webhook = app(WebhookProcessor::class)->handle('monipay', $raw, $signature, $payload);

        $this->assertSame('processed', $webhook->status);
        $this->assertSame(0, Contribution::query()->where('member_id', $member->id)->count());
        $this->assertSame(750000, app(WalletService::class)->balance($member));
        $this->assertSame(1, FinancialTransaction::query()->where('member_id', $member->id)->where('type', 'wallet_credit')->count());
    }
}
