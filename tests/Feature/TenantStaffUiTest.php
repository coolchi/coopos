<?php

namespace Tests\Feature;

use App\Domain\Membership\MemberService;
use App\Enums\CooperativeMode;
use App\Enums\CooperativeType;
use App\Enums\FinanceMode;
use App\Enums\UserType;
use App\Models\Activity;
use App\Models\Announcement;
use App\Models\Contribution;
use App\Models\ContributionEnrollment;
use App\Models\ContributionProduct;
use App\Models\IslamicFinancingProduct;
use App\Models\LoanProduct;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantStaffUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_renders_and_shares_approval_counts(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Dashboard')
                ->has('kpis.active_members')
                ->has('recent_financing')
                ->has('branches.0', fn ($branch) => $branch
                    ->has('uuid')
                    ->has('name')
                    ->has('admin')
                    ->has('staff_count')
                    ->has('members_count')
                    ->has('payments_total')
                    ->etc()
                )
            );
    }

    public function test_staff_can_update_a_member_profile(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.okeke@example.com',
            'phone' => '08011111111',
        ]);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/members/{$member->uuid}", [
                'first_name' => 'Adaora',
                'last_name' => 'Okeke',
                'email' => 'ada.okeke@example.com',
                'phone' => '08022222222',
                'occupation' => 'Teacher',
                'next_of_kin_name' => 'Chidi Okeke',
                'next_of_kin_phone' => '08033333333',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'Adaora',
            'occupation' => 'Teacher',
        ]);
    }

    public function test_staff_can_add_an_existing_member_as_guarantor(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $service = app(MemberService::class);

        $guarantor = $service->register([
            'first_name' => 'Bola',
            'last_name' => 'Ade',
            'email' => 'bola.guarantor@example.com',
        ]);
        $member = $service->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.guarantor@example.com',
        ]);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/members/{$member->uuid}/guarantors", [
                'guarantor_uuid' => $guarantor->uuid,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('member_guarantors', [
            'member_id' => $member->id,
            'guarantor_member_id' => $guarantor->id,
        ]);

        $this->get("/{$tenant->slug}/members/{$member->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Members/Show')
                ->where('member.guarantors.0.name', $guarantor->fullName())
                ->where('member.guarantors.0.membership_number', $guarantor->membership_number)
            );

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/members/{$member->uuid}/guarantors", [
                'guarantor_uuid' => $member->uuid,
            ])
            ->assertSessionHasErrors('guarantor_uuid');
    }

    public function test_staff_can_open_a_member_profile(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.okeke@example.com',
        ]);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/members/{$member->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Members/Show')
                ->where('member.name', 'Ada Okeke')
                ->has('kpis.savings')
                ->has('kpis.plans')
                ->has('member.enrollments')
                ->has('member.bank_accounts')
                ->has('member.nin')
            );
    }

    public function test_staff_can_save_nin_on_the_member_profile(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $service = app(MemberService::class);
        $member = $service->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.nin@example.com',
            'nin' => '12345678901',
        ]);

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/members/{$member->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Members/Show')
                ->where('member.nin', '12345678901')
            );

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/members/{$member->uuid}", [
                'first_name' => 'Ada',
                'last_name' => 'Okeke',
                'nin' => '10987654321',
            ])
            ->assertRedirect();

        $this->get("/{$tenant->slug}/members/{$member->uuid}")
            ->assertInertia(fn ($page) => $page->where('member.nin', '10987654321'));
    }

    public function test_member_profile_paginates_recent_contributions(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.recent@example.com',
        ]);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Member savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 0,
            'is_active' => true,
        ]);

        foreach (range(1, 9) as $i) {
            Contribution::query()->create([
                'member_id' => $member->id,
                'contribution_product_id' => $product->id,
                'amount_kobo' => 100000 * $i,
                'paid_on' => now()->subDays(10 - $i)->toDateString(),
                'channel' => 'cash',
            ]);
        }

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/members/{$member->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Members/Show')
                ->has('member.contributions.data', 8)
                ->where('member.contributions.per_page', 8)
                ->where('member.contributions.total', 9)
                ->where('member.contributions.current_page', 1)
            );

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/members/{$member->uuid}?contributions_page=2")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('member.contributions.data', 1)
                ->where('member.contributions.current_page', 2)
            );
    }

    public function test_activity_log_records_the_actor_device(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
        ]);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:153.0) Gecko/20100101 Firefox/153.0')
            ->post("/{$tenant->slug}/members/{$member->uuid}", [
                'first_name' => 'Adaora',
                'last_name' => 'Okeke',
            ]);

        $log = Activity::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Firefox on macOS', $log->getExtraProperty('device'));
        $this->assertNotEmpty($log->getExtraProperty('ip'));
    }

    public function test_staff_can_invite_another_officer(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings/users", [
                'name' => 'Bola Secretary',
                'email' => 'bola.secretary@example.com',
                'password' => 'Password1',
                'role' => 'Secretary',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'bola.secretary@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame(UserType::Staff, $user->type);
        setPermissionsTeamId($tenant->id);
        $this->assertTrue($user->hasRole('Secretary'));
    }

    public function test_reports_page_includes_portfolio_detail(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/reports")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Reports/Index')
                ->has('membership.active')
                ->has('savings.lifetime')
                ->has('monthly')
                ->has('top_savers')
            );
    }

    public function test_member_statement_page_loads_without_a_selected_member(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/reports/statement")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Reports/Statement'));
    }

    public function test_settings_hub_pages_render(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)->get("/{$tenant->slug}/settings")->assertOk();
        $this->actingAs($admin)->get("/{$tenant->slug}/settings/policies")->assertOk();
        $this->actingAs($admin)->get("/{$tenant->slug}/settings/access")->assertOk();
        $this->actingAs($admin)->get("/{$tenant->slug}/settings/activity")->assertOk();
        $this->actingAs($admin)->get("/{$tenant->slug}/settings/workflows")->assertOk();
    }

    public function test_staff_can_upload_an_organization_logo(): void
    {
        Storage::fake('public');
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings", [
                'name' => $tenant->name,
                'membership_number_prefix' => 'M',
                'membership_fee' => 0,
                'currency' => 'NGN',
                'logo' => UploadedFile::fake()->image('crest.png', 120, 120),
            ])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertNotNull($tenant->logo_path);
        Storage::disk('public')->assertExists($tenant->logo_path);
        $this->assertNotNull($tenant->logoUrl());
    }

    public function test_staff_can_set_an_organization_slogan(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/settings")
            ->post("/{$tenant->slug}/settings", [
                'name' => $tenant->name,
                'slogan' => 'Together we grow',
                'membership_number_prefix' => 'M',
                'membership_fee' => 0,
                'currency' => 'NGN',
            ])
            ->assertRedirect("/{$tenant->slug}/settings");

        $this->assertSame('Together we grow', $tenant->fresh()->slogan);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tenant.slogan', 'Together we grow'));

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/settings")
            ->post("/{$tenant->slug}/settings", [
                'name' => $tenant->name,
                'slogan' => '',
                'membership_number_prefix' => 'M',
                'membership_fee' => 0,
                'currency' => 'NGN',
            ])
            ->assertRedirect("/{$tenant->slug}/settings");

        $this->assertNull($tenant->fresh()->slogan);
    }

    public function test_staff_can_choose_an_organization_theme(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/settings")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Settings/Organization')
                ->has('themes', 7)
                ->where('tenant.theme', 'teal')
            );

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/settings")
            ->post("/{$tenant->slug}/settings", [
                'name' => $tenant->name,
                'theme' => 'crimson',
                'membership_number_prefix' => 'M',
                'membership_fee' => 0,
                'currency' => 'NGN',
            ])
            ->assertRedirect("/{$tenant->slug}/settings");

        $tenant->refresh();
        $this->assertSame('crimson', $tenant->theme);
        $this->assertSame('#d7050c', $tenant->brand_color);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenant.theme.key', 'crimson')
                ->where('tenant.theme.brand', '#d7050c')
            );
    }

    public function test_staff_can_update_operating_policies(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/settings/policies", [
                'require_membership_approval' => false,
                'require_verified_bank_for_payout' => true,
                'guarantor_exposure_cap' => 250000,
                'max_active_loans' => 2,
                'max_debt_to_savings_percent' => 150,
                'loan_min_savings_percent' => 40,
                'loan_min_shares_percent' => 5,
                'loan_required_product_uuids' => [],
                'loan_check_attendance' => true,
                'loan_max_absences_per_year' => 2,
                'email_notifications' => true,
                'sms_notifications' => false,
            ])
            ->assertRedirect();

        $settings = $tenant->settings()->first();
        $this->assertFalse($settings->require_membership_approval);
        $this->assertSame(2, $settings->max_active_loans);
        $this->assertSame(40, $settings->loan_min_savings_percent);
        $this->assertSame(5, $settings->loan_min_shares_percent);
        $this->assertSame([], $settings->loan_required_product_uuids);
        $this->assertSame(2, $settings->loan_max_absences_per_year);
    }

    public function test_staff_can_record_multiple_payments_for_a_member(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.pay@example.com',
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
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/members")
            ->post("/{$tenant->slug}/contributions", [
                'member_uuid' => $member->uuid,
                'payments' => [
                    ['product_uuid' => $product->uuid, 'amount' => 5000, 'channel' => 'cash'],
                    ['product_uuid' => $product->uuid, 'amount' => 2500, 'channel' => 'transfer'],
                ],
            ])
            ->assertRedirect("/{$tenant->slug}/members");

        $this->assertSame(2, Contribution::query()->where('member_id', $member->id)->count());
    }

    public function test_enrolling_below_product_minimum_returns_validation_error(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.enroll@example.com',
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
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/contributions")
            ->post("/{$tenant->slug}/contributions/enroll", [
                'member_uuid' => $member->uuid,
                'product_uuid' => $product->uuid,
                'amount' => 500,
            ])
            ->assertRedirect("/{$tenant->slug}/contributions")
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, ContributionEnrollment::query()->count());
    }

    public function test_enrolling_twice_on_the_same_plan_updates_instead_of_failing(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);

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

        $csrf = PreventRequestForgery::class;

        $this->actingAs($officer)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/contributions")
            ->post("/{$tenant->slug}/contributions/enroll", [
                'member_uuid' => $member->uuid,
                'product_uuid' => $product->uuid,
                'amount' => 5000,
            ])
            ->assertRedirect("/{$tenant->slug}/contributions");

        $this->actingAs($officer)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/contributions")
            ->post("/{$tenant->slug}/contributions/enroll", [
                'member_uuid' => $member->uuid,
                'product_uuid' => $product->uuid,
                'amount' => 8000,
            ])
            ->assertRedirect("/{$tenant->slug}/contributions")
            ->assertSessionHas('success');

        $this->assertSame(1, ContributionEnrollment::query()->count());
        $this->assertSame(800000, ContributionEnrollment::query()->value('amount_kobo'));
    }

    public function test_enrollments_page_renders_and_records_a_month_for_required_products(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $csrf = PreventRequestForgery::class;

        $member = app(MemberService::class)->register([
            'first_name' => 'Bola',
            'last_name' => 'Ade',
        ]);
        app(MemberService::class)->approve($member);

        $required = ContributionProduct::query()->create([
            'name' => 'Share',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 100000,
            'is_active' => true,
            'is_required' => true,
        ]);
        $optional = ContributionProduct::query()->create([
            'name' => 'Target scheme',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 200000,
            'is_active' => true,
            'is_required' => false,
        ]);

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/contributions/enrollments")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Savings/Enrollments'));

        $this->actingAs($officer)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/contributions/enrollments")
            ->post("/{$tenant->slug}/contributions/enroll", [
                'member_uuid' => $member->uuid,
                'product_uuid' => $optional->uuid,
                'amount' => 2000,
            ])
            ->assertRedirect();

        $this->actingAs($officer)
            ->get("/{$tenant->slug}/contributions")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'summary')
                ->has('columns')
                ->has('rows')
                ->has('required_products')
                ->has("member_plans.{$member->uuid}", 1)
            );

        $this->actingAs($officer)
            ->withoutMiddleware($csrf)
            ->post("/{$tenant->slug}/contributions", [
                'member_uuid' => $member->uuid,
                'for_month' => '2026-08',
                'payments' => [[
                    'product_uuid' => $required->uuid,
                    'amount' => 1000,
                    'channel' => 'cash',
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('2026-08-01', Contribution::query()->first()?->for_month?->toDateString());
        $this->assertSame('cash', Contribution::query()->value('channel'));
    }

    public function test_staff_can_draft_publish_and_remove_an_announcement(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $csrf = PreventRequestForgery::class;

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/communication")
            ->post("/{$tenant->slug}/communication", [
                'title' => 'AGM next week',
                'body' => 'Please attend.',
                'audience' => 'all',
                'publish' => false,
            ])
            ->assertRedirect("/{$tenant->slug}/communication");

        $announcement = Announcement::query()->first();
        $this->assertNotNull($announcement);
        $this->assertNull($announcement->published_at);

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->post("/{$tenant->slug}/communication/{$announcement->uuid}/publish")
            ->assertRedirect();

        $this->assertNotNull($announcement->fresh()->published_at);

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->delete("/{$tenant->slug}/communication/{$announcement->uuid}")
            ->assertRedirect();

        $this->assertSame(0, Announcement::query()->count());
    }

    public function test_staff_can_open_reply_and_close_a_support_ticket(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $csrf = PreventRequestForgery::class;

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->post("/{$tenant->slug}/support", [
                'subject' => 'Payout delay',
                'category' => 'payment',
                'priority' => 'high',
                'body' => 'A withdrawal has not landed.',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->first();
        $this->assertNotNull($ticket);
        $this->assertSame('open', $ticket->status);
        $this->assertSame(1, $ticket->messages()->count());

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/support/{$ticket->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Support/Show')->where('ticket.subject', 'Payout delay'));

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->post("/{$tenant->slug}/support/{$ticket->uuid}/reply", [
                'body' => 'Member number M0001.',
            ])
            ->assertRedirect();

        $this->assertSame(2, $ticket->messages()->count());

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->post("/{$tenant->slug}/support/{$ticket->uuid}/close")
            ->assertRedirect();

        $this->assertSame('closed', $ticket->fresh()->status);
    }

    public function test_staff_can_manage_products(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $csrf = PreventRequestForgery::class;

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/products")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Products/Index')->has('products'));

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/products")
            ->post("/{$tenant->slug}/products", [
                'kind' => 'savings',
                'name' => 'Monthly thrift',
                'finance_mode' => 'conventional',
                'frequency' => 'monthly',
                'minimum_amount' => 1000,
                'maximum_amount' => 50000,
                'default_amount' => 5000,
            ])
            ->assertRedirect("/{$tenant->slug}/products");

        $savings = ContributionProduct::query()->first();
        $this->assertNotNull($savings);
        $this->assertSame('Monthly thrift', $savings->name);
        $this->assertSame(500000, $savings->default_amount_kobo);

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/products")
            ->post("/{$tenant->slug}/products", [
                'kind' => 'loan',
                'name' => 'Emergency loan',
                'minimum_amount' => 10000,
                'maximum_amount' => 200000,
                'minimum_tenure_months' => 1,
                'maximum_tenure_months' => 6,
                'interest_method' => 'reducing_balance',
                'interest_rate' => 10,
                'processing_fee' => 500,
                'guarantors_required' => 1,
            ])
            ->assertRedirect("/{$tenant->slug}/products");

        $loan = LoanProduct::query()->first();
        $this->assertNotNull($loan);

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->post("/{$tenant->slug}/products/loan/{$loan->uuid}", [
                'name' => 'Staff emergency loan',
                'minimum_amount' => 10000,
                'maximum_amount' => 250000,
                'minimum_tenure_months' => 1,
                'maximum_tenure_months' => 6,
                'interest_method' => 'reducing_balance',
                'interest_rate' => 10,
            ])
            ->assertRedirect();

        $this->assertSame('Staff emergency loan', $loan->fresh()->name);

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->post("/{$tenant->slug}/products/savings/{$savings->uuid}/toggle")
            ->assertRedirect();

        $this->assertFalse($savings->fresh()->is_active);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/savings/products")
            ->assertRedirect("/{$tenant->slug}/products");
    }

    public function test_islamic_staff_can_create_an_islamic_product(): void
    {
        [$tenant, $admin] = $this->makeCooperative([
            'type' => CooperativeType::Islamic->value,
            'mode' => CooperativeMode::Islamic->value,
        ]);
        $csrf = PreventRequestForgery::class;

        $this->actingAs($admin)
            ->withoutMiddleware($csrf)
            ->from("/{$tenant->slug}/products")
            ->post("/{$tenant->slug}/products", [
                'kind' => 'islamic',
                'name' => 'Qard Hasan',
                'structure' => 'qard_hasan',
                'minimum_amount' => 20000,
                'maximum_amount' => 400000,
                'admin_fee' => 500,
            ])
            ->assertRedirect("/{$tenant->slug}/products");

        $this->assertSame(1, IslamicFinancingProduct::query()->count());
        $this->assertSame('qard_hasan', IslamicFinancingProduct::query()->first()->structure->value);
    }

    public function test_islamic_finance_book_renders(): void
    {
        [$tenant, $admin] = $this->makeCooperative([
            'type' => CooperativeType::Islamic->value,
            'mode' => CooperativeMode::Islamic->value,
        ]);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/loan")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Islamic/Index')
                ->has('kpis.qard_outstanding')
                ->has('kpis.murabaha_outstanding')
                ->has('products')
                ->has('qard')
                ->has('murabaha')
            );

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/islamic")
            ->assertRedirect("/{$tenant->slug}/loan");
    }
}
