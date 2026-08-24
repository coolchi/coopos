<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_platform_admin(): void
    {
        $this->get('/platform')->assertRedirect('/login');
    }

    public function test_super_admin_can_open_the_control_plane(): void
    {
        $admin = User::factory()->platform()->create();

        $this->actingAs($admin)->get('/platform')->assertOk();
        $this->actingAs($admin)->get('/platform/cooperatives')->assertOk();
        $this->actingAs($admin)->get('/platform/invoices')->assertOk();
        $this->actingAs($admin)->get('/platform/settings')->assertOk();
    }

    public function test_platform_settings_can_be_saved(): void
    {
        $admin = User::factory()->platform()->create();

        $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->post('/platform/settings', [
            'platform_name' => 'CoopOS Control',
            'tagline' => 'Nigerian cooperatives',
            'support_email' => 'samuel.w@example.com',
            'support_phone' => '08030000000',
            'from_email' => 'nathan.k@example.net',
            'trial_days' => 21,
            'allow_registration' => true,
            'require_cooperative_approval' => false,
            'default_plan' => 'professional',
            'central_domain' => 'coopos.test',
            'subdomains_enabled' => true,
            'maintenance_mode' => false,
            'allow_impersonation' => true,
            'paystack_public_key' => 'pk_test_demo',
            'sms_enabled' => false,
            'islamic_finance_enabled' => true,
            'hybrid_mode_enabled' => true,
            'api_enabled' => true,
        ])->assertRedirect();

        $this->assertSame('CoopOS Control', PlatformSetting::get('platform_name'));
        $this->assertSame(21, PlatformSetting::get('trial_days'));
    }

    public function test_super_admin_can_login_as_tenant_admin(): void
    {
        [$tenant, $staff] = $this->makeCooperative();
        $admin = User::factory()->platform()->create();

        $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->post("/platform/cooperatives/{$tenant->slug}/impersonate")
            ->assertRedirect($tenant->appUrl('/dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->assertSame($tenant->id, session('impersonate_tenant_id'));
    }

    public function test_login_as_forces_a_full_browser_redirect_for_inertia(): void
    {
        [$tenant, $staff] = $this->makeCooperative();
        $admin = User::factory()->platform()->create();

        $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post("/platform/cooperatives/{$tenant->slug}/impersonate")
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', $tenant->appUrl('/dashboard'));

        $this->assertAuthenticatedAs($staff);
    }

    public function test_platform_cooperatives_does_not_crash_when_a_staff_user_has_a_branch(): void
    {
        [$tenant, $staff] = $this->makeCooperative();
        $branch = $tenant->branches()->first();
        $staff->forceFill(['branch_id' => $branch?->id])->save();
        TenantContext::forget();

        $this->actingAs($staff->fresh())
            ->get('/platform/cooperatives')
            ->assertForbidden();
    }

    public function test_return_to_platform_uses_the_central_host(): void
    {
        [$tenant, $staff] = $this->makeCooperative();
        $admin = User::factory()->platform()->create();

        $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->post("/platform/cooperatives/{$tenant->slug}/impersonate");

        $this->actingAs($staff)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->post('/impersonation/leave')
            ->assertRedirect(\App\Support\Tenancy::centralUrl('/platform'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_operators_page_lists_only_admins_and_agents(): void
    {
        $admin = User::factory()->platform()->create(['name' => 'Ada Admin']);
        $agent = User::factory()->agent()->create(['name' => 'Femi Agent']);
        [$tenant, $staff] = $this->makeCooperative();
        $member = User::factory()->create([
            'type' => \App\Enums\UserType::Member,
            'tenant_id' => $tenant->id,
            'name' => 'Member User',
        ]);
        TenantContext::forget();

        $this->actingAs($admin)
            ->get('/platform/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Users')
                ->has('users.data', 2)
                ->where('users.data', fn ($rows) => collect($rows)->pluck('role')->sort()->values()->all() === ['admin', 'agent']
                    && collect($rows)->pluck('name')->sort()->values()->all() === ['Ada Admin', 'Femi Agent'])
            );

        $this->actingAs($admin)
            ->get('/platform/users?role=admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.role', 'admin')
            );

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }

    public function test_platform_admin_can_create_an_agent(): void
    {
        $admin = User::factory()->platform()->create();

        $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
            ->post('/platform/users', [
                'name' => 'Kemi Agent',
                'email' => 'tom.h@example.org',
                'password' => 'Password1',
                'role' => 'agent',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'tom.h@example.org',
            'type' => 'platform',
            'is_platform_admin' => 0,
        ]);
    }
}
