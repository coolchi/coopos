<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_renders(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->has('demo_logins')
                ->where('demo_logins.0.email', 'nathan.k@example.net')
            );
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Register')
                ->has('plans')
                ->has('types')
                ->has('modes')
                ->has('states')
                ->has('trial_days')
            );
    }

    public function test_authenticated_staff_is_sent_to_workspace_from_auth_pages(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get('/login')
            ->assertRedirect(route('tenant.dashboard', $tenant->slug));

        $this->actingAs($admin)
            ->get('/register')
            ->assertRedirect(route('tenant.dashboard', $tenant->slug));
    }

    public function test_open_workspace_sends_platform_admin_to_the_control_plane(): void
    {
        $admin = \App\Models\User::factory()->platform()->create();

        $this->actingAs($admin)
            ->get('/home')
            ->assertRedirect(\App\Support\Tenancy::centralUrl('/platform'));
    }

    public function test_open_workspace_sends_staff_to_their_cooperative(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get('/home')
            ->assertRedirect($tenant->appUrl('/dashboard'));
    }
}
