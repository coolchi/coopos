<?php

namespace Tests\Feature;

use App\Domain\Savings\ContributionService;
use App\Enums\FinanceMode;
use App\Enums\MemberStatus;
use App\Enums\UserType;
use App\Models\ContributionProduct;
use App\Models\Member;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionTransactionManageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_edit_and_delete_with_access_code(): void
    {
        [$tenant] = $this->makeCooperative();
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);
        $officer = $this->makeHqOfficer($tenant);

        $product = ContributionProduct::query()->create([
            'name' => 'Monthly savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 500000,
            'is_active' => true,
        ]);

        $member = Member::query()->create([
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'membership_number' => 'M00001',
            'status' => MemberStatus::Active,
            'joined_on' => now()->toDateString(),
            'branch_id' => $this->headquartersId($tenant),
        ]);

        $contribution = app(ContributionService::class)->recordPayment(
            $member,
            $product,
            500000,
            'cash',
            forMonth: now()->format('Y-m'),
        );

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->put("/{$tenant->slug}/contributions/{$contribution->uuid}", [
                'access_code' => '1234',
                'product_uuid' => $product->uuid,
                'amount' => 6000,
                'channel' => 'cash',
                'paid_on' => now()->toDateString(),
                'for_month' => now()->format('Y-m'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contributions', [
            'uuid' => $contribution->uuid,
            'amount_kobo' => 600000,
        ]);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->delete("/{$tenant->slug}/contributions/{$contribution->uuid}", [
                'access_code' => '1234',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('contributions', [
            'uuid' => $contribution->uuid,
        ]);
    }

    public function test_non_admin_cannot_manage_transactions(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);

        $staff = User::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => UserType::Staff,
        ]);
        $staff->assignRole('Treasurer');

        $product = ContributionProduct::query()->create([
            'name' => 'Monthly savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'is_active' => true,
        ]);

        $member = Member::query()->create([
            'first_name' => 'Bola',
            'last_name' => 'Ade',
            'membership_number' => 'M00002',
            'status' => MemberStatus::Active,
            'joined_on' => now()->toDateString(),
        ]);

        $contribution = app(ContributionService::class)->recordPayment(
            $member,
            $product,
            200000,
            'cash',
            forMonth: now()->format('Y-m'),
        );

        $this->actingAs($staff)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->delete("/{$tenant->slug}/contributions/{$contribution->uuid}", [
                'access_code' => '1234',
            ])
            ->assertForbidden();
    }

    public function test_wrong_access_code_is_rejected(): void
    {
        [$tenant] = $this->makeCooperative();
        TenantContext::set($tenant);
        $officer = $this->makeHqOfficer($tenant);

        $product = ContributionProduct::query()->create([
            'name' => 'Monthly savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'is_active' => true,
        ]);

        $member = Member::query()->create([
            'first_name' => 'Chi',
            'last_name' => 'Eze',
            'membership_number' => 'M00003',
            'status' => MemberStatus::Active,
            'joined_on' => now()->toDateString(),
            'branch_id' => $this->headquartersId($tenant),
        ]);

        $contribution = app(ContributionService::class)->recordPayment(
            $member,
            $product,
            200000,
            'cash',
            forMonth: now()->format('Y-m'),
        );

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/contributions?tab=transactions")
            ->delete("/{$tenant->slug}/contributions/{$contribution->uuid}", [
                'access_code' => 'wrong',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('access_code');

        $this->assertDatabaseHas('contributions', [
            'uuid' => $contribution->uuid,
        ]);
    }
}
