<?php

namespace Tests\Feature;

use App\Domain\Membership\MemberService;
use App\Enums\ContributionBucket;
use App\Enums\FinanceMode;
use App\Models\Branch;
use App\Models\ContributionProduct;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_lock_brought_forward_and_cannot_post_after(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.cutover@example.com',
        ]);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Savings',
            'bucket' => ContributionBucket::Savings,
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 0,
            'is_active' => true,
        ]);

        $payload = [
            'year' => now()->year,
            'entries' => [[
                'member_uuid' => $member->uuid,
                'product_uuid' => $product->uuid,
                'amount' => 10000,
            ]],
        ];

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/opening-balances", $payload)
            ->assertRedirect();

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/opening-balances/lock")
            ->assertRedirect();

        $tenant->refresh()->load('settings');
        $this->assertNotEmpty($tenant->settings->extras['opening_balances_locked_at'] ?? null);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/opening-balances", $payload)
            ->assertForbidden();
    }

    public function test_super_admin_can_unlock_with_access_code(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $extras = $tenant->settings->extras ?? [];
        $extras['opening_balances_locked_at'] = now()->toIso8601String();
        $extras['opening_balances_locked_by'] = $admin->id;
        $tenant->settings->update(['extras' => $extras]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/opening-balances/unlock", [
                'access_code' => 'wrong',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('access_code');

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/opening-balances/unlock", [
                'access_code' => '1234',
            ])
            ->assertRedirect();

        $tenant->refresh()->load('settings');
        $this->assertEmpty($tenant->settings->extras['opening_balances_locked_at'] ?? null);
    }

    public function test_tenant_admin_can_filter_opening_balances_by_branch(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $hq = $tenant->branches()->where('is_headquarters', true)->firstOrFail();
        $ikeja = Branch::query()->create([
            'name' => 'Ikeja',
            'code' => 'IKEJA',
            'city' => 'Ikeja',
            'state' => 'Lagos',
        ]);

        $hqMember = app(MemberService::class)->register([
            'first_name' => 'HQ',
            'last_name' => 'Ada',
            'email' => 'hq.ob@example.com',
            'branch_id' => $hq->id,
        ]);
        $ikejaMember = app(MemberService::class)->register([
            'first_name' => 'Ikeja',
            'last_name' => 'Bola',
            'email' => 'ikeja.ob@example.com',
            'branch_id' => $ikeja->id,
        ]);
        app(MemberService::class)->approve($hqMember);
        app(MemberService::class)->approve($ikejaMember);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/opening-balances")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Savings/OpeningBalances')
                ->where('can_filter_branches', true)
                ->where('needs_branch', true)
                ->has('branches', 2)
                ->has('rows', 0)
            );

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/opening-balances?branch={$ikeja->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can_filter_branches', true)
                ->where('needs_branch', false)
                ->where('branch.uuid', $ikeja->uuid)
                ->has('rows', 1)
                ->where('rows.0.name', 'Ikeja Bola')
            );

        $officer = $this->makeHqOfficer($tenant);
        $this->actingAs($officer)
            ->get("/{$tenant->slug}/opening-balances")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can_filter_branches', false)
                ->where('needs_branch', false)
                ->has('branches', 0)
                ->has('rows', 1)
                ->where('rows.0.name', 'HQ Ada')
            );
    }
}
