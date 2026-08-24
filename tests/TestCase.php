<?php

namespace Tests;

use App\Domain\Ledger\LedgerService;
use App\Domain\Platform\TenantProvisioner;
use App\Enums\CooperativeMode;
use App\Enums\CooperativeType;
use App\Enums\UserType;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        TenantContext::forget();
    }

    protected function tearDown(): void
    {
        TenantContext::forget();
        parent::tearDown();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    protected function makeCooperative(array $overrides = []): array
    {
        $provisioner = app(TenantProvisioner::class);
        $provisioner->ensurePlans();

        $admin = User::factory()->create([
            'type' => UserType::Staff,
            'email' => fake()->unique()->safeEmail(),
        ]);

        $tenant = $provisioner->onboard([
            'name' => $overrides['name'] ?? fake()->company().' Cooperative',
            'type' => $overrides['type'] ?? CooperativeType::Staff->value,
            'mode' => $overrides['mode'] ?? CooperativeMode::General->value,
            'state' => 'Lagos',
            'plan' => 'professional',
        ], $admin);

        $provisioner->activate($tenant);
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);

        return [$tenant->fresh(), $admin->fresh()];
    }

    protected function makeHqOfficer(Tenant $tenant, array $overrides = []): User
    {
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);

        $hq = $tenant->branches()->where('is_headquarters', true)->first();

        $officer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $hq?->id,
            'type' => UserType::Staff,
            'name' => $overrides['name'] ?? 'HQ Officer',
            'email' => $overrides['email'] ?? fake()->unique()->safeEmail(),
        ]);
        $officer->assignRole('Branch Admin');

        return $officer->fresh();
    }

    protected function headquartersId(Tenant $tenant): ?int
    {
        return $tenant->branches()->where('is_headquarters', true)->value('id');
    }

    protected function fundMemberForLoan(Member $member, int $amountKobo): void
    {
        $savings = (int) ceil($amountKobo * 0.5);
        $shares = (int) ceil($amountKobo * 0.1);
        $ledger = app(LedgerService::class);

        if ($savings > 0) {
            $ledger->recordTransaction(
                type: 'opening_balance',
                amountKobo: $savings,
                journalLines: [
                    ['account' => '1010', 'debit' => $savings, 'memo' => 'Test savings'],
                    ['account' => '2100', 'credit' => $savings, 'member' => $member, 'memo' => 'Savings'],
                ],
                member: $member,
                idempotencyKey: 'test-savings:'.$member->uuid.':'.$savings,
            );
        }

        if ($shares > 0) {
            $ledger->recordTransaction(
                type: 'opening_balance',
                amountKobo: $shares,
                journalLines: [
                    ['account' => '1010', 'debit' => $shares, 'memo' => 'Test shares'],
                    ['account' => '3000', 'credit' => $shares, 'member' => $member, 'memo' => 'Shares'],
                ],
                member: $member,
                idempotencyKey: 'test-shares:'.$member->uuid.':'.$shares,
            );
        }
    }
}
