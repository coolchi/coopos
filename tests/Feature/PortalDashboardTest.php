<?php

namespace Tests\Feature;

use App\Domain\Ledger\WalletService;
use App\Domain\Membership\MemberService;
use App\Domain\Savings\ContributionService;
use App\Enums\FinanceMode;
use App\Models\Contribution;
use App\Models\ContributionEnrollment;
use App\Models\ContributionProduct;
use App\Models\MemberVirtualAccount;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_portal_shows_virtual_account_mandates_and_paginated_contributions(): void
    {
        [$tenant] = $this->makeCooperative();
        $member = app(MemberService::class)->register([
            'first_name' => 'Einar',
            'last_name' => 'Boyer',
            'email' => 'einar.portal@example.com',
            'password' => 'Password1',
        ]);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Member savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 0,
            'default_amount_kobo' => 1500000,
            'is_active' => true,
        ]);
        app(ContributionService::class)->enroll($member, $product, 1500000);

        $required = ContributionProduct::query()->create([
            'name' => 'Development levy',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'yearly',
            'minimum_amount_kobo' => 0,
            'default_amount_kobo' => 200000,
            'is_active' => true,
            'is_required' => true,
        ]);
        app(ContributionService::class)->enroll($member, $required, 200000);

        MemberVirtualAccount::query()->create([
            'member_id' => $member->id,
            'provider' => 'monipay',
            'account_number' => '1111234567',
            'account_name' => 'EINAR BOYER',
            'bank_name' => 'Monipay MFB',
            'status' => 'approved',
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

        $this->actingAs($member->user)
            ->get("/{$tenant->slug}/portal")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Dashboard')
                ->where('virtual_account.account_number', '1111234567')
                ->has('mandates', 1)
                ->where('mandates.0.product', 'Member savings')
                ->where('mandates.0.product_uuid', $product->uuid)
                ->where('mandates.0.amount_naira', 15000)
                ->where('wallet', '₦0')
                ->has('shares')
                ->has('recent.data', 8)
                ->where('recent.per_page', 8)
                ->where('recent.total', 9)
            );

        $this->actingAs($member->user)
            ->get("/{$tenant->slug}/portal?page=2")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('recent.data', 1)
                ->where('recent.current_page', 2)
            );

        $this->actingAs($member->user)
            ->get("/{$tenant->slug}/portal/apply")
            ->assertRedirect("/{$tenant->slug}/portal/loans");

        $this->actingAs($member->user)
            ->get("/{$tenant->slug}/portal/wallet")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Wallet')
                ->where('wallet', '₦0')
                ->has('activity')
            );

        $this->actingAs($member->user)
            ->get("/{$tenant->slug}/portal/loans")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/Loans'));

        $this->actingAs($member->user)
            ->get("/{$tenant->slug}/portal/repayments")
            ->assertNotFound();
    }

    public function test_member_can_post_multiple_portal_contributions(): void
    {
        [$tenant] = $this->makeCooperative();
        $member = app(MemberService::class)->register([
            'first_name' => 'Adaeze',
            'last_name' => 'Okonkwo',
            'email' => 'adaeze.portal@example.com',
            'password' => 'Password1',
        ]);
        app(MemberService::class)->approve($member);

        $savings = ContributionProduct::query()->create([
            'name' => 'Member savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 0,
            'default_amount_kobo' => 2000000,
            'is_active' => true,
        ]);
        $share = ContributionProduct::query()->create([
            'name' => 'Share',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 0,
            'default_amount_kobo' => 1000000,
            'is_active' => true,
        ]);

        app(WalletService::class)->credit($member, 3_000_000, 'manual', 'test-wallet:'.$member->uuid);

        $this->actingAs($member->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/portal")
            ->post("/{$tenant->slug}/portal/contribute", [
                'for_month' => now()->format('Y-m'),
                'paid_on' => now()->toDateString(),
                'payments' => [
                    [
                        'product_uuid' => $savings->uuid,
                        'amount' => 20000,
                    ],
                    [
                        'product_uuid' => $share->uuid,
                        'amount' => 10000,
                    ],
                ],
            ])
            ->assertRedirect("/{$tenant->slug}/portal")
            ->assertSessionHas('success', '2 contributions received.');

        $this->assertSame(2, Contribution::query()->where('member_id', $member->id)->count());
        $savingsPayment = Contribution::query()->where('contribution_product_id', $savings->id)->first();
        $sharePayment = Contribution::query()->where('contribution_product_id', $share->id)->first();
        $this->assertSame(2000000, $savingsPayment?->amount_kobo);
        $this->assertSame(1000000, $sharePayment?->amount_kobo);
        $this->assertSame('member_portal', $savingsPayment?->channel);
        $this->assertSame(now()->startOfMonth()->toDateString(), $savingsPayment?->for_month?->toDateString());
        $this->assertSame(0, app(WalletService::class)->balance($member));
    }

    public function test_legacy_single_portal_contribution_still_posts(): void
    {
        [$tenant] = $this->makeCooperative();
        $member = app(MemberService::class)->register([
            'first_name' => 'Chidi',
            'last_name' => 'Okafor',
            'email' => 'chidi.portal@example.com',
            'password' => 'Password1',
        ]);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Member savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 0,
            'is_active' => true,
        ]);

        app(WalletService::class)->credit($member, 1_500_000, 'manual', 'test-wallet:'.$member->uuid);

        $this->actingAs($member->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/portal")
            ->post("/{$tenant->slug}/portal/contribute", [
                'product_uuid' => $product->uuid,
                'amount' => 15000,
            ])
            ->assertRedirect("/{$tenant->slug}/portal")
            ->assertSessionHas('success', 'Contribution received.');

        $posted = Contribution::query()->where('member_id', $member->id)->first();
        $this->assertSame(1500000, $posted?->amount_kobo);
        $this->assertSame('member_portal', $posted?->channel);
    }

    public function test_portal_contribution_requires_wallet_balance(): void
    {
        [$tenant] = $this->makeCooperative();
        $member = app(MemberService::class)->register([
            'first_name' => 'Bisi',
            'last_name' => 'Ade',
            'email' => 'bisi.portal@example.com',
            'password' => 'Password1',
        ]);
        app(MemberService::class)->approve($member);

        $product = ContributionProduct::query()->create([
            'name' => 'Member savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($member->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/portal")
            ->post("/{$tenant->slug}/portal/contribute", [
                'product_uuid' => $product->uuid,
                'amount' => 5000,
            ])
            ->assertRedirect("/{$tenant->slug}/portal")
            ->assertSessionHasErrors('payments');

        $this->assertSame(0, Contribution::query()->where('member_id', $member->id)->count());
    }

    public function test_member_can_add_edit_and_delete_mandates(): void
    {
        [$tenant] = $this->makeCooperative();
        $member = app(MemberService::class)->register([
            'first_name' => 'Kemi',
            'last_name' => 'Bello',
            'email' => 'kemi.mandate@example.com',
            'password' => 'Password1',
        ]);
        app(MemberService::class)->approve($member);

        $savings = ContributionProduct::query()->create([
            'name' => 'Member savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 500000,
            'is_active' => true,
        ]);
        $share = ContributionProduct::query()->create([
            'name' => 'Share',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 5000000,
            'is_active' => true,
        ]);

        $csrf = PreventRequestForgery::class;
        $home = "/{$tenant->slug}/portal";

        $this->actingAs($member->user)
            ->withoutMiddleware($csrf)
            ->from($home)
            ->post("{$home}/mandates", [
                'product_uuid' => $savings->uuid,
                'amount' => 5000,
            ])
            ->assertRedirect($home)
            ->assertSessionHas('success', 'Mandate added.');

        $enrollment = ContributionEnrollment::query()
            ->where('member_id', $member->id)
            ->where('contribution_product_id', $savings->id)
            ->first();

        $this->assertNotNull($enrollment);
        $this->assertSame(500000, $enrollment->amount_kobo);

        $this->actingAs($member->user)
            ->withoutMiddleware($csrf)
            ->from($home)
            ->patch("{$home}/mandates/{$enrollment->uuid}", [
                'amount' => 8000,
            ])
            ->assertRedirect($home)
            ->assertSessionHas('success', 'Mandate updated.');

        $this->assertSame(800000, $enrollment->fresh()->amount_kobo);

        $required = ContributionProduct::query()->create([
            'name' => 'Development levy',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'yearly',
            'minimum_amount_kobo' => 0,
            'default_amount_kobo' => 200000,
            'is_active' => true,
            'is_required' => true,
        ]);

        $this->actingAs($member->user)
            ->withoutMiddleware($csrf)
            ->from($home)
            ->post("{$home}/mandates", [
                'product_uuid' => $required->uuid,
                'amount' => 2000,
            ])
            ->assertRedirect($home)
            ->assertSessionHasErrors('product_uuid');

        $this->assertSame(0, ContributionEnrollment::query()->where('contribution_product_id', $required->id)->count());

        $this->actingAs($member->user)
            ->withoutMiddleware($csrf)
            ->from($home)
            ->post("{$home}/mandates", [
                'product_uuid' => $savings->uuid,
                'amount' => 9000,
            ])
            ->assertRedirect($home)
            ->assertSessionHasErrors('product_uuid');

        $this->actingAs($member->user)
            ->withoutMiddleware($csrf)
            ->from($home)
            ->post("{$home}/mandates", [
                'product_uuid' => $share->uuid,
                'amount' => 500,
            ])
            ->assertRedirect($home)
            ->assertSessionHasErrors('amount');

        $this->actingAs($member->user)
            ->withoutMiddleware($csrf)
            ->from($home)
            ->delete("{$home}/mandates/{$enrollment->uuid}")
            ->assertRedirect($home)
            ->assertSessionHas('success', 'Mandate removed.');

        $this->assertSame(0, ContributionEnrollment::query()->where('member_id', $member->id)->count());
    }

    public function test_member_cannot_change_another_members_mandate(): void
    {
        [$tenant] = $this->makeCooperative();
        $owner = app(MemberService::class)->register([
            'first_name' => 'Tunde',
            'last_name' => 'Ade',
            'email' => 'tunde.mandate@example.com',
            'password' => 'Password1',
        ]);
        $other = app(MemberService::class)->register([
            'first_name' => 'Ngozi',
            'last_name' => 'Eze',
            'email' => 'ngozi.mandate@example.com',
            'password' => 'Password1',
        ]);
        app(MemberService::class)->approve($owner);
        app(MemberService::class)->approve($other);

        $product = ContributionProduct::query()->create([
            'name' => 'Member savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 0,
            'default_amount_kobo' => 500000,
            'is_active' => true,
        ]);
        $enrollment = app(ContributionService::class)->enroll($owner, $product, 500000);

        $this->actingAs($other->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/portal")
            ->patch("/{$tenant->slug}/portal/mandates/{$enrollment->uuid}", [
                'amount' => 9000,
            ])
            ->assertNotFound();

        $this->actingAs($other->user)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->from("/{$tenant->slug}/portal")
            ->delete("/{$tenant->slug}/portal/mandates/{$enrollment->uuid}")
            ->assertNotFound();

        $this->assertSame(500000, $enrollment->fresh()->amount_kobo);
    }
}
