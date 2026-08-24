<?php

namespace Tests\Feature;

use App\Domain\Membership\MemberService;
use App\Domain\Savings\ContributionService;
use App\Enums\FinanceMode;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\ContributionProduct;
use App\Models\Member;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_creates_headquarters_and_members_must_belong_to_it(): void
    {
        [$tenant] = $this->makeCooperative();

        $hq = Branch::query()->where('is_headquarters', true)->first();
        $this->assertNotNull($hq);
        $this->assertSame('HQ', $hq->code);

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
        ]);

        $this->assertSame($hq->id, $member->branch_id);
    }

    public function test_central_admin_sees_all_branches_and_can_create_one(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/branches")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Branches/Index')
                ->has('branches', 1)
            );

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches", [
                'name' => 'Ikeja',
                'code' => 'IKEJA',
                'state' => 'Lagos',
                'city' => 'Ikeja',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $tenant->id,
            'code' => 'IKEJA',
        ]);
    }

    public function test_branch_admin_only_sees_their_members_and_payments(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        setPermissionsTeamId($tenant->id);

        $hq = Branch::query()->where('is_headquarters', true)->firstOrFail();
        $ikeja = Branch::query()->create([
            'name' => 'Ikeja',
            'code' => 'IKEJA',
            'city' => 'Ikeja',
            'state' => 'Lagos',
        ]);

        $hqMember = app(MemberService::class)->register([
            'first_name' => 'HQ',
            'last_name' => 'Member',
            'branch_id' => $hq->id,
        ]);
        $ikejaMember = app(MemberService::class)->register([
            'first_name' => 'Ikeja',
            'last_name' => 'Member',
            'branch_id' => $ikeja->id,
        ]);

        $product = ContributionProduct::query()->create([
            'name' => 'Monthly savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 2000000,
        ]);
        $contrib = app(ContributionService::class);
        $contrib->recordPayment($hqMember, $product, 2000000, 'manual', 'hq-pay');
        $contrib->recordPayment($ikejaMember, $product, 1500000, 'manual', 'ikeja-pay');

        $branchAdmin = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $ikeja->id,
            'type' => UserType::Staff,
            'name' => 'Ikeja Admin',
            'email' => 'ikeja.admin@example.com',
            'password' => 'Password1',
            'email_verified_at' => now(),
        ]);
        $branchAdmin->assignRole('Branch Admin');
        $ikeja->update(['admin_user_id' => $branchAdmin->id]);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/members")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('counts.all', 2));

        $this->actingAs($branchAdmin)
            ->get("/{$tenant->slug}/members")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.all', 1)
                ->where('members.data.0.name', 'Ikeja Member')
            );

        $this->actingAs($branchAdmin)
            ->get("/{$tenant->slug}/contributions?tab=transactions")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('contributions.data.0.member', 'Ikeja Member'));

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/branches")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('branches', 2));
    }

    public function test_central_admin_can_update_a_branch_and_cannot_deactivate_headquarters(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $hq = Branch::query()->where('is_headquarters', true)->firstOrFail();
        $ikeja = Branch::query()->create([
            'name' => 'Ikeja',
            'code' => 'IKEJA',
            'city' => 'Ikeja',
            'state' => 'Lagos',
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/branches/{$ikeja->uuid}")
            ->post("/{$tenant->slug}/branches/{$ikeja->uuid}", [
                'name' => 'Ikeja Annex',
                'code' => 'IKEJA',
                'city' => 'Ikeja',
                'state' => 'Lagos',
                'phone' => '08011112222',
                'is_active' => false,
                'admin_user_uuid' => $admin->uuid,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'id' => $ikeja->id,
            'name' => 'Ikeja Annex',
            'is_active' => 0,
            'admin_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches/{$hq->uuid}", [
                'name' => $hq->name,
                'code' => $hq->code,
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertTrue($hq->fresh()->is_active);
    }

    public function test_central_admin_can_deactivate_and_activate_a_branch(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $hq = Branch::query()->where('is_headquarters', true)->firstOrFail();
        $ikeja = Branch::query()->create([
            'name' => 'Ikeja',
            'code' => 'IKEJA',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches/{$ikeja->uuid}/status", ['is_active' => 0])
            ->assertRedirect();

        $this->assertFalse($ikeja->fresh()->is_active);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches/{$ikeja->uuid}/status", ['is_active' => 1])
            ->assertRedirect();

        $this->assertTrue($ikeja->fresh()->is_active);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/branches/{$hq->uuid}")
            ->post("/{$tenant->slug}/branches/{$hq->uuid}/status", ['is_active' => 0])
            ->assertRedirect();

        $this->assertTrue($hq->fresh()->is_active);
    }

    public function test_branch_admin_cannot_see_another_branch_member(): void
    {
        [$tenant] = $this->makeCooperative();
        setPermissionsTeamId($tenant->id);

        $hq = Branch::query()->where('is_headquarters', true)->firstOrFail();
        $ikeja = Branch::query()->create(['name' => 'Ikeja', 'code' => 'IKEJA']);

        $hqMember = app(MemberService::class)->register([
            'first_name' => 'HQ',
            'last_name' => 'Only',
            'branch_id' => $hq->id,
        ]);

        $branchAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $ikeja->id,
            'type' => UserType::Staff,
        ]);
        $branchAdmin->assignRole('Branch Admin');

        $this->actingAs($branchAdmin)
            ->get("/{$tenant->slug}/members/{$hqMember->uuid}")
            ->assertNotFound();

        BranchContext::forget();
        $this->assertTrue(Member::query()->whereKey($hqMember->id)->exists());
    }

    public function test_central_admin_can_login_as_branch_admin_and_return(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        setPermissionsTeamId($tenant->id);

        $ikeja = Branch::query()->create(['name' => 'Ikeja', 'code' => 'IKEJA']);
        $branchAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $ikeja->id,
            'type' => UserType::Staff,
            'name' => 'Ikeja Admin',
        ]);
        $branchAdmin->assignRole('Branch Admin');
        $ikeja->update(['admin_user_id' => $branchAdmin->id]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches/{$ikeja->uuid}/login-as")
            ->assertRedirect();

        $this->assertAuthenticatedAs($branchAdmin);
        $this->assertSame($admin->id, session('staff_impersonator_id'));

        $this->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('staff_impersonation.as', 'Ikeja Admin')
                ->where('staff_impersonation.original', $admin->name)
            );

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/login-as/leave")
            ->assertRedirect();

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('staff_impersonator_id'));
    }

    public function test_central_admin_can_login_as_a_branch_they_administer(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $hq = Branch::query()->where('is_headquarters', true)->firstOrFail();
        $hq->update(['admin_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches/{$hq->uuid}/login-as")
            ->assertRedirect("/{$tenant->slug}/dashboard");

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('staff_impersonator_id'));
        $this->assertSame($hq->uuid, session('branch_context_uuid'));

        $this->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('branch.uuid', $hq->uuid)
                ->where('staff_impersonation', null)
            );
    }

    public function test_login_as_prefers_a_branch_officer_over_the_central_admin(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        setPermissionsTeamId($tenant->id);

        $ikeja = Branch::query()->create(['name' => 'Ikeja', 'code' => 'IKEJA']);
        $ikeja->update(['admin_user_id' => $admin->id]);

        $branchAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $ikeja->id,
            'type' => UserType::Staff,
            'name' => 'Ikeja Officer',
        ]);
        $branchAdmin->assignRole('Branch Admin');

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches/{$ikeja->uuid}/login-as")
            ->assertRedirect();

        $this->assertAuthenticatedAs($branchAdmin);
        $this->assertSame($admin->id, session('staff_impersonator_id'));
    }

    public function test_central_admin_can_switch_branch_context(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $ikeja = Branch::query()->create(['name' => 'Ikeja', 'code' => 'IKEJA']);
        app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'HQ',
        ]);
        app(MemberService::class)->register([
            'first_name' => 'Bola',
            'last_name' => 'Ikeja',
            'branch_id' => $ikeja->id,
        ]);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/members")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.all', 2)
                ->has('accessible_branches', 2)
                ->where('can_view_all_branches', true)
                ->where('branch', null)
            );

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/members")
            ->post("/{$tenant->slug}/current-branch", ['branch_uuid' => $ikeja->uuid])
            ->assertRedirect("/{$tenant->slug}/members")
            ->assertSessionHas('branch_context_uuid', $ikeja->uuid);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/members")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.all', 1)
                ->where('members.data.0.name', 'Bola Ikeja')
                ->where('branch.uuid', $ikeja->uuid)
            );

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/members")
            ->post("/{$tenant->slug}/current-branch", ['branch_uuid' => 'all'])
            ->assertRedirect("/{$tenant->slug}/members");

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/members")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.all', 2)
                ->where('branch', null)
            );
    }

    public function test_branch_admin_cannot_switch_branch_context(): void
    {
        [$tenant] = $this->makeCooperative();
        setPermissionsTeamId($tenant->id);

        $ikeja = Branch::query()->create(['name' => 'Ikeja', 'code' => 'IKEJA']);
        $branchAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $ikeja->id,
            'type' => UserType::Staff,
        ]);
        $branchAdmin->assignRole('Branch Admin');

        $this->actingAs($branchAdmin)
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('accessible_branches', 1)
                ->where('can_view_all_branches', false)
            );

        $this->actingAs($branchAdmin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/current-branch", ['branch_uuid' => 'all'])
            ->assertForbidden();
    }

    public function test_branch_admin_cannot_login_as_another_officer(): void
    {
        [$tenant] = $this->makeCooperative();
        setPermissionsTeamId($tenant->id);

        $ikeja = Branch::query()->create(['name' => 'Ikeja', 'code' => 'IKEJA']);
        $branchAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $ikeja->id,
            'type' => UserType::Staff,
        ]);
        $branchAdmin->assignRole('Branch Admin');
        $ikeja->update(['admin_user_id' => $branchAdmin->id]);

        $this->actingAs($branchAdmin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches/{$ikeja->uuid}/login-as")
            ->assertForbidden();
    }

    public function test_central_admin_can_assign_staff_and_move_a_member(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        setPermissionsTeamId($tenant->id);

        $hq = Branch::query()->where('is_headquarters', true)->firstOrFail();
        $ikeja = Branch::query()->create(['name' => 'Ikeja', 'code' => 'IKEJA']);
        $officer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $hq->id,
            'type' => UserType::Staff,
        ]);
        $officer->assignRole('Loan Officer');

        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'branch_id' => $hq->id,
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches/{$ikeja->uuid}/staff", [
                'staff_user_uuid' => $officer->uuid,
            ])
            ->assertRedirect();

        $this->assertSame($ikeja->id, $officer->fresh()->branch_id);

        $this->actingAs($admin)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/branches/{$hq->uuid}/transfer-member", [
                'member_uuid' => $member->uuid,
                'destination_uuid' => $ikeja->uuid,
            ])
            ->assertRedirect();

        $this->assertSame($ikeja->id, $member->fresh()->branch_id);
    }
}
