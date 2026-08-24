<?php

namespace Tests\Feature;

use App\Domain\Membership\MemberService;
use App\Enums\ContributionBucket;
use App\Enums\FinanceMode;
use App\Models\Contribution;
use App\Models\ContributionProduct;
use App\Models\Meeting;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoopExcelOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_record_an_introducer_on_a_member(): void
    {
        [$tenant, $admin] = $this->makeCooperative();
        $service = app(MemberService::class);

        $sponsor = $service->register([
            'first_name' => 'Bola',
            'last_name' => 'Ade',
            'email' => 'bola.ade@example.com',
        ]);
        $service->approve($sponsor);

        $member = $service->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.intro@example.com',
            'sponsor_member_id' => $sponsor->id,
        ]);

        $this->assertSame($sponsor->id, $member->fresh()->sponsor_member_id);

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/members/{$member->uuid}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Members/Show')
                ->where('member.introducer', $sponsor->fullName())
            );
    }

    public function test_staff_can_post_opening_balances_once(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.bf@example.com',
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
            ->post("/{$tenant->slug}/opening-balances", $payload)
            ->assertRedirect();

        $this->assertSame(1, Contribution::query()->where('channel', 'opening')->count());
        $this->assertSame(1000000, (int) Contribution::query()->where('channel', 'opening')->value('amount_kobo'));
    }

    public function test_staff_can_mark_meeting_attendance(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $member = app(MemberService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Okeke',
            'email' => 'ada.roll@example.com',
        ]);
        app(MemberService::class)->approve($member);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/attendance", [
                'title' => 'July meeting',
                'held_on' => now()->toDateString(),
                'kind' => 'general',
            ])
            ->assertRedirect();

        $meeting = Meeting::query()->firstOrFail();

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/attendance/{$meeting->uuid}", [
                'present' => [$member->uuid],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meeting_attendances', [
            'meeting_id' => $meeting->id,
            'member_id' => $member->id,
            'present' => 1,
            'status' => 'present',
        ]);
    }

    public function test_staff_can_mark_permitted_absence(): void
    {
        [$tenant] = $this->makeCooperative();
        $officer = $this->makeHqOfficer($tenant);
        $member = app(MemberService::class)->register([
            'first_name' => 'Bola',
            'last_name' => 'Ade',
            'email' => 'bola.roll@example.com',
        ]);
        app(MemberService::class)->approve($member);

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/attendance", [
                'title' => 'Committee sitting',
                'held_on' => now()->toDateString(),
                'kind' => 'committee',
            ])
            ->assertRedirect();

        $meeting = Meeting::query()->firstOrFail();

        $this->actingAs($officer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post("/{$tenant->slug}/attendance/{$meeting->uuid}", [
                'attendance' => [
                    $member->uuid => 'permitted',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meeting_attendances', [
            'meeting_id' => $meeting->id,
            'member_id' => $member->id,
            'present' => 0,
            'status' => 'permitted',
        ]);
    }

    public function test_accounts_report_renders(): void
    {
        [$tenant, $admin] = $this->makeCooperative();

        $this->actingAs($admin)
            ->get("/{$tenant->slug}/reports/accounts")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Reports/Accounts')
                ->has('assets')
                ->has('liabilities')
            );
    }
}
