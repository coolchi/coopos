<?php

namespace Tests\Feature;

use App\Domain\Savings\ContributionService;
use App\Enums\ContributionBucket;
use App\Enums\FinanceMode;
use App\Enums\MemberStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserType;
use App\Models\ContributionProduct;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\FiscalYearContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalManageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_edit_and_delete_with_access_code(): void
    {
        [$tenant, $admin, $member, $product, $withdrawal] = $this->seedPendingWithdrawal();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->put("/{$tenant->slug}/withdrawals/{$withdrawal->uuid}", [
                'access_code' => '1234',
                'member_uuid' => $member->uuid,
                'product_uuid' => $product->uuid,
                'amount' => 2500,
                'reason' => 'Corrected amount',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('withdrawal_requests', [
            'uuid' => $withdrawal->uuid,
            'amount_kobo' => 250000,
            'reason' => 'Corrected amount',
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->delete("/{$tenant->slug}/withdrawals/{$withdrawal->uuid}", [
                'access_code' => '1234',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('withdrawal_requests', [
            'uuid' => $withdrawal->uuid,
        ]);
    }

    public function test_super_admin_can_edit_paid_withdrawal_and_repost_ledger(): void
    {
        [$tenant, $admin, $member, $product, $withdrawal] = $this->seedPendingWithdrawal();

        $this->actingAs($admin);
        $paid = app(ContributionService::class)->approveWithdrawal($withdrawal);
        $originalTxnId = $paid->financial_transaction_id;

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->put("/{$tenant->slug}/withdrawals/{$paid->uuid}", [
                'access_code' => '1234',
                'member_uuid' => $member->uuid,
                'product_uuid' => $product->uuid,
                'amount' => 3000,
                'reason' => 'Adjusted payout',
            ])
            ->assertRedirect();

        $paid->refresh();
        $this->assertSame(300000, $paid->amount_kobo);
        $this->assertNotSame($originalTxnId, $paid->financial_transaction_id);
        $this->assertSame(
            TransactionStatus::Reversed,
            FinancialTransaction::query()->find($originalTxnId)?->status,
        );
    }

    public function test_non_admin_cannot_manage_withdrawals(): void
    {
        [$tenant, $admin, $member, $product, $withdrawal] = $this->seedPendingWithdrawal();

        $staff = User::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => UserType::Staff,
        ]);
        $staff->assignRole('Treasurer');

        $this->actingAs($staff)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->delete("/{$tenant->slug}/withdrawals/{$withdrawal->uuid}", [
                'access_code' => '1234',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('withdrawal_requests', [
            'uuid' => $withdrawal->uuid,
        ]);
    }

    public function test_wrong_access_code_is_rejected(): void
    {
        [$tenant, $admin, $member, $product, $withdrawal] = $this->seedPendingWithdrawal();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/withdrawals")
            ->delete("/{$tenant->slug}/withdrawals/{$withdrawal->uuid}", [
                'access_code' => 'wrong',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('access_code');

        $this->assertDatabaseHas('withdrawal_requests', [
            'uuid' => $withdrawal->uuid,
        ]);
    }

    public function test_withdrawals_can_be_filtered_by_month(): void
    {
        [$tenant, $admin, $member, $product, $withdrawal] = $this->seedPendingWithdrawal();
        $period = FiscalYearContext::period();
        $thisMonth = now()->format('Y-m');
        $otherMonth = $period->start->format('Y-m') === $thisMonth
            ? $period->end->format('Y-m')
            : $period->start->format('Y-m');

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/withdrawals?month={$thisMonth}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Savings/Withdrawals')
                ->where('month', $thisMonth)
                ->has('months')
                ->has('withdrawals.data', 1)
                ->where('withdrawals.data.0.uuid', $withdrawal->uuid)
            );

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/withdrawals?month={$otherMonth}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('month', $otherMonth)
                ->has('withdrawals.data', 0)
            );
    }

    public function test_withdrawal_records_the_bank_sent_to(): void
    {
        [$tenant, $admin, $member, $product] = $this->seedPendingWithdrawal();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/withdrawals", [
                'member_uuid' => $member->uuid,
                'product_uuid' => $product->uuid,
                'amount' => 1500,
                'reason' => 'Rent',
                'bank_name' => 'GTBank',
                'account_number' => '0123456789',
                'account_name' => 'Ari King',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('member_bank_accounts', [
            'member_id' => $member->id,
            'bank_name' => 'GTBank',
            'account_number' => '0123456789',
            'account_name' => 'Ari King',
        ]);
        $this->assertDatabaseHas('withdrawal_requests', [
            'member_id' => $member->id,
            'reason' => 'Rent',
            'amount_kobo' => 150000,
        ]);
        $this->assertNotNull(
            WithdrawalRequest::query()->where('reason', 'Rent')->first()?->member_bank_account_id
        );

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/withdrawals")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Savings/Withdrawals')
                ->where('withdrawals.data.0.sent_to', 'GTBank')
                ->where('withdrawals.data.0.sent_to_account', '0123456789')
                ->has('members.0.bank_accounts')
            );
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Member, 3: ContributionProduct, 4: WithdrawalRequest}
     */
    private function seedPendingWithdrawal(): array
    {
        [$tenant, $admin] = $this->makeCooperative();
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);

        $product = ContributionProduct::query()->create([
            'name' => 'Special Target Scheme',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'bucket' => ContributionBucket::SpecialTarget,
            'minimum_amount_kobo' => 100000,
            'is_active' => true,
            'is_locked' => false,
        ]);

        $member = Member::query()->create([
            'first_name' => 'Ari',
            'last_name' => 'King',
            'membership_number' => 'M00011',
            'status' => MemberStatus::Active,
            'joined_on' => now()->toDateString(),
            'branch_id' => $this->headquartersId($tenant),
        ]);

        $savings = app(ContributionService::class);
        $savings->recordPayment($member, $product, 1000000, 'cash', forMonth: now()->format('Y-m'));
        $withdrawal = $savings->requestWithdrawal($member, $product, 200000, 'School fees');
        $officer = $this->makeHqOfficer($tenant);

        return [$tenant, $officer, $member, $product, $withdrawal];
    }
}
