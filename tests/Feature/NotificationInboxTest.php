<?php

namespace Tests\Feature;

use App\Domain\Membership\MemberService;
use App\Domain\Savings\ContributionService;
use App\Enums\FinanceMode;
use App\Enums\MemberStatus;
use App\Enums\UserType;
use App\Models\ContributionProduct;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shares_notification_inbox(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Dashboard')
                ->where('notifications.unread', 0)
                ->has('notifications.recent')
            );
    }

    public function test_necessary_actions_and_announcements_notify_the_right_people(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.notify@example.com',
            'password' => 'Password1',
            'phone' => '08011111111',
        ]);

        $this->assertSame(MemberStatus::Pending, $member->status);
        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());

        app(MemberService::class)->approve($member);
        $memberUser = $member->user()->first();
        $this->assertNotNull($memberUser);
        $this->assertSame(1, $memberUser->fresh()->unreadNotifications()->count());
        $this->assertSame('Membership approved', $memberUser->unreadNotifications()->first()->data['title']);

        $product = ContributionProduct::query()->create([
            'name' => 'Monthly savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'is_active' => true,
        ]);

        app(ContributionService::class)->recordPayment(
            $member->fresh(),
            $product,
            500000,
            'cash',
            forMonth: now()->format('Y-m'),
        );

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
        $this->assertSame(2, $memberUser->fresh()->unreadNotifications()->count());

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/communication")
            ->post("/{$tenant->slug}/communication", [
                'title' => 'AGM next Saturday',
                'body' => 'All members should attend at the secretariat.',
                'audience' => 'all',
                'publish' => true,
            ])
            ->assertRedirect();

        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());
        $this->assertSame('New announcement', $admin->unreadNotifications()->first()->data['title']);
        $titles = $memberUser->fresh()->unreadNotifications()->get()->pluck('data.title');
        $this->assertTrue($titles->contains('New announcement'));
        $this->assertSame(3, $memberUser->fresh()->unreadNotifications()->count());
    }

    public function test_user_can_mark_notifications_read(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $this->actingAs($admin);

        $member = app(MemberService::class)->register([
            'first_name' => 'Chidi',
            'last_name' => 'Okafor',
            'email' => 'chidi.notify@example.com',
            'password' => 'Password1',
        ]);
        app(MemberService::class)->approve($member);

        $user = $member->user()->first();
        $notification = $user->unreadNotifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/portal")
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($admin);
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);
        app(ContributionService::class)->recordPayment(
            $member->fresh(),
            ContributionProduct::query()->create([
                'name' => 'Thrift',
                'finance_mode' => FinanceMode::Conventional,
                'frequency' => 'monthly',
                'minimum_amount_kobo' => 100000,
                'is_active' => true,
            ]),
            200000,
            'cash',
            forMonth: now()->format('Y-m'),
        );

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());

        $this->actingAs($user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/portal")
            ->post('/notifications/read-all')
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_other_staff_are_notified_of_pending_membership(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $approver = $this->makeHqOfficer($tenant);

        $officer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => UserType::Staff,
        ]);
        setPermissionsTeamId($tenant->id);
        $officer->assignRole('Secretary');

        $this->actingAs($officer);
        app(MemberService::class)->register([
            'first_name' => 'Bola',
            'last_name' => 'Adeyemi',
            'email' => 'bola.notify@example.com',
            'password' => 'Password1',
        ]);

        $this->assertSame(0, $officer->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
        $this->assertGreaterThan(0, $approver->fresh()->unreadNotifications()->count());
        $this->assertSame('Membership pending', $approver->unreadNotifications()->first()->data['title']);
    }
}
