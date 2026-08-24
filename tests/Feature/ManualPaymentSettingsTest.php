<?php

namespace Tests\Feature;

use App\Domain\Membership\MemberService;
use App\Enums\FinanceMode;
use App\Enums\UserType;
use App\Models\Contribution;
use App\Models\ContributionProduct;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_toggle_manual_payment_posting(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $csrf = PreventRequestForgery::class;

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/settings")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Settings/Organization')
                ->where('settings.can_set_manual_payments', true)
                ->where('settings.allow_manual_payments', true)
            );

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/settings")
            ->post("/{$tenant->slug}/settings", [
                'name' => $tenant->name,
                'membership_number_prefix' => 'M',
                'membership_fee' => 0,
                'currency' => 'NGN',
                'allow_manual_payments' => false,
            ])
            ->assertRedirect("/{$tenant->slug}/settings");

        $this->assertFalse($tenant->settings->fresh()->allowsManualPayments());

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/contributions")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('allow_manual_payments', false));
    }

    public function test_non_admin_cannot_change_manual_payment_setting(): void
    {
        [$tenant] = $this->makeCooperative();
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);

        $staff = User::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => UserType::Staff,
        ]);
        $staff->assignRole('Treasurer');

        $this->actingAs($staff)
            ->get("/{$tenant->slug}/settings")
            ->assertForbidden();

        $this->actingAs($staff)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings", [
                'name' => $tenant->name,
                'membership_number_prefix' => 'M',
                'membership_fee' => 0,
                'currency' => 'NGN',
                'allow_manual_payments' => false,
            ])
            ->assertForbidden();

        $this->assertTrue($tenant->settings->fresh()->allowsManualPayments());
    }

    public function test_staff_cannot_record_payments_when_manual_posting_is_off(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $csrf = PreventRequestForgery::class;

        $extras = $tenant->settings->extras ?? [];
        $extras['allow_manual_payments'] = false;
        $tenant->settings->update(['extras' => $extras]);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
        ]);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Monthly savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 500000,
            'is_active' => true,
        ]);

        $this->actingAs($officer)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/contributions")
            ->post("/{$tenant->slug}/contributions", [
                'member_uuid' => $member->uuid,
                'payments' => [[
                    'product_uuid' => $product->uuid,
                    'amount' => 5000,
                    'channel' => 'transfer',
                ]],
            ])
            ->assertRedirect("/{$tenant->slug}/contributions")
            ->assertSessionHas('error');

        $this->assertSame(0, Contribution::query()->count());
    }
}
