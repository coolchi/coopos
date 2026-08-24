<?php

namespace Tests\Feature;

use App\Domain\Membership\MemberService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_view_members_but_cannot_create_them(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $hq = $tenant->branches()->where('is_headquarters', true)->firstOrFail();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/members")
            ->assertOk();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/members", [
                'first_name' => 'Ada',
                'last_name' => 'Okeke',
                'branch_uuid' => $hq->uuid,
            ])
            ->assertForbidden();
    }

    public function test_branch_admin_can_create_members_but_cannot_open_settings_or_branches(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/members", [
                'first_name' => 'Ada',
                'last_name' => 'Okeke',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('members', [
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
        ]);

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/settings")
            ->assertForbidden();

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/branches")
            ->assertForbidden();
    }

    public function test_tenant_admin_can_manage_settings_and_products_but_not_approvals(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)->get("/{$tenant->slug}/settings")->assertOk();
        $this->actingAs($admin)->get("/{$tenant->slug}/products")->assertOk();
        $this->actingAs($admin)->get("/{$tenant->slug}/approvals")->assertForbidden();
    }

    public function test_branch_admin_cannot_open_products_or_support(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);

        $this->actingAs($officer)->get("/{$tenant->slug}/products")->assertForbidden();
        $this->actingAs($officer)->get("/{$tenant->slug}/support")->assertForbidden();
    }

    public function test_tenant_admin_cannot_record_contributions(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.access@example.com',
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/contributions", [
                'member_uuid' => $member->uuid,
                'payments' => [['product_uuid' => '00000000-0000-0000-0000-000000000000', 'amount' => 1000]],
            ])
            ->assertForbidden();
    }
}
