<?php

namespace Tests\Feature;

use App\Enums\AppMode;
use App\Enums\CooperativeMode;
use App\Models\DemoSeedRecord;
use App\Models\Member;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_page_is_available_and_defaults_to_demo_mode(): void
    {
        [$tenant, $admin] = $this->makeCooperative(['mode' => CooperativeMode::Islamic->value]);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/settings/demo")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Settings/DemoData')
                ->where('app_mode', 'demo')
                ->where('is_demo', true)
                ->has('features')
            );
    }

    public function test_live_mode_rejects_seeding(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $tenant->settings->update(['app_mode' => AppMode::Live]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/settings/demo")
            ->post("/{$tenant->slug}/settings/demo/seed", [
                'features' => ['members'],
            ])
            ->assertRedirect("/{$tenant->slug}/settings/demo")
            ->assertSessionHas('error');

        $this->assertSame(0, Member::query()->count());
    }

    public function test_can_switch_to_live_and_back_to_demo(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings/demo/mode", ['app_mode' => 'live'])
            ->assertRedirect();

        $this->assertSame(AppMode::Live, $tenant->settings->fresh()->appMode());

        $this->post("/{$tenant->slug}/settings/demo/mode", ['app_mode' => 'demo'])
            ->assertRedirect();

        $this->assertSame(AppMode::Demo, $tenant->settings->fresh()->appMode());
    }

    public function test_seeds_nigerian_members_and_resets_them(): void
    {
        [$tenant, $admin] = $this->makeCooperative(['mode' => CooperativeMode::Islamic->value]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings/demo/seed", [
                'features' => ['members'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $members = Member::query()->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(8, $members->count());
        $this->assertTrue($members->every(fn (Member $m) => str_contains((string) $m->email, "demo.{$tenant->slug}.")));
        $this->assertContains($members->first()->first_name, [
            'Adewale', 'Chinedu', 'Ibrahim', 'Emeka', 'Tunde', 'Yusuf', 'Olumide', 'Nnamdi',
            'Abubakar', 'Kayode', 'Aisha', 'Chioma', 'Funke', 'Ngozi', 'Fatima', 'Yetunde',
        ]);
        $this->assertGreaterThan(0, DemoSeedRecord::query()->count());

        $this->post("/{$tenant->slug}/settings/demo/reset", [
            'features' => ['members'],
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, Member::query()->count());
        $this->assertSame(0, DemoSeedRecord::query()->count());
    }

    public function test_feature_selection_seeds_only_announcements(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings/demo/seed", [
                'features' => ['announcements'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, Member::query()->count());
        $this->assertGreaterThan(0, DemoSeedRecord::query()->where('feature', 'announcements')->count());
        $this->assertSame(0, DemoSeedRecord::query()->where('feature', 'members')->count());
    }

    public function test_seeds_savings_islamic_and_accounting_then_resets_all(): void
    {
        [$tenant, $admin] = $this->makeCooperative(['mode' => CooperativeMode::Islamic->value]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings/demo/seed", [
                'features' => ['members', 'savings', 'islamic', 'accounting', 'attendance', 'announcements'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertGreaterThan(0, Member::query()->count());
        $this->assertGreaterThan(0, DemoSeedRecord::query()->where('feature', 'savings')->count());
        $this->assertGreaterThan(0, DemoSeedRecord::query()->where('feature', 'accounting')->count());

        $this->post("/{$tenant->slug}/settings/demo/reset", [
            'features' => ['members', 'savings', 'islamic', 'accounting', 'attendance', 'announcements'],
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, Member::query()->count());
        $this->assertSame(0, DemoSeedRecord::query()->count());
    }

    public function test_conventional_tenant_can_seed_loans_and_withdrawals(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings/demo/seed", [
                'features' => ['members', 'savings', 'withdrawals', 'loans'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertGreaterThan(0, DemoSeedRecord::query()->where('feature', 'savings')->count());

        $this->post("/{$tenant->slug}/settings/demo/reset", [
            'features' => ['members'],
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, Member::query()->count());
        $this->assertSame(0, DemoSeedRecord::query()->count());
    }
}
