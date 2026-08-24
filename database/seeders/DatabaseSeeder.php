<?php

namespace Database\Seeders;

use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Domain\Lending\LoanService;
use App\Domain\Membership\MemberService;
use App\Domain\Platform\TenantProvisioner;
use App\Domain\Savings\ContributionService;
use App\Enums\CooperativeMode;
use App\Enums\CooperativeType;
use App\Enums\FinanceMode;
use App\Enums\InterestMethod;
use App\Enums\IslamicStructure;
use App\Enums\LoanStatus;
use App\Enums\ShariahStatus;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\ContributionProduct;
use App\Models\IslamicFinancingProduct;
use App\Models\LoanProduct;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $provisioner = app(TenantProvisioner::class);
        $provisioner->ensurePlans();

        User::query()->create([
            'name' => 'CoopOS Super Admin',
            'email' => 'nathan.k@example.net',
            'password' => 'Password1',
            'type' => UserType::Platform,
            'is_platform_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->conventional($provisioner);
        $this->islamic($provisioner);
        $this->hybrid($provisioner);
        $this->call(PlatformDemoSeeder::class);
    }

    private function conventional(TenantProvisioner $provisioner): void
    {
        $admin = User::query()->create([
            'name' => 'Adewale Treasurer',
            'email' => 'ursula.b@example.com',
            'password' => 'Password1',
            'type' => UserType::Staff,
            'email_verified_at' => now(),
        ]);

        $tenant = $provisioner->onboard([
            'name' => 'Ijebu Staff Cooperative',
            'slug' => 'ijebu',
            'type' => CooperativeType::Staff->value,
            'mode' => CooperativeMode::General->value,
            'state' => 'Ogun',
            'plan' => 'professional',
        ], $admin);
        $provisioner->activate($tenant);
        TenantContext::set($tenant);
        $this->seedHqOfficer($tenant, 'Funke Operations', 'hq.ijebu@coopos.test');

        $savings = ContributionProduct::query()->create([
            'name' => 'Monthly savings',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 500000,
            'maximum_amount_kobo' => 10000000,
            'default_amount_kobo' => 2000000,
        ]);

        $loanProduct = LoanProduct::query()->create([
            'name' => 'Personal loan',
            'minimum_amount_kobo' => 1000000,
            'maximum_amount_kobo' => 50000000,
            'minimum_tenure_months' => 3,
            'maximum_tenure_months' => 12,
            'interest_method' => InterestMethod::ReducingBalance,
            'interest_rate_bps' => 1200,
            'processing_fee_kobo' => 100000,
            'guarantors_required' => 0,
            'repayment_frequency' => 'monthly',
        ]);

        $this->seedMembers($tenant, $savings, $loanProduct, null, 12, true);
    }

    private function islamic(TenantProvisioner $provisioner): void
    {
        $admin = User::query()->create([
            'name' => 'Aisha Manager',
            'email' => 'xena.w@example.org',
            'password' => 'Password1',
            'type' => UserType::Staff,
            'email_verified_at' => now(),
        ]);

        $tenant = $provisioner->onboard([
            'name' => 'Al-Barakah Cooperative',
            'slug' => 'barakah',
            'type' => CooperativeType::Islamic->value,
            'mode' => CooperativeMode::Islamic->value,
            'state' => 'Kano',
            'plan' => 'professional',
        ], $admin);
        $provisioner->activate($tenant);
        TenantContext::set($tenant);
        $this->seedHqOfficer($tenant, 'Zainab Operations', 'hq.barakah@coopos.test');

        $savings = ContributionProduct::query()->create([
            'name' => 'Member savings',
            'finance_mode' => FinanceMode::Islamic,
            'frequency' => 'monthly',
            'minimum_amount_kobo' => 200000,
            'default_amount_kobo' => 1500000,
        ]);

        $qard = IslamicFinancingProduct::query()->create([
            'name' => 'Qard Hasan',
            'structure' => IslamicStructure::QardHasan,
            'minimum_amount_kobo' => 2000000,
            'maximum_amount_kobo' => 30000000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'admin_fee_kobo' => 50000,
            'shariah_status' => ShariahStatus::Approved,
        ]);
        $qard->shariahApprovals()->create([
            'adviser_name' => 'Sheikh Abdullahi',
            'status' => ShariahStatus::Approved,
            'approved_on' => now()->toDateString(),
            'notes' => 'Benevolent loan. No riba.',
        ]);

        $this->seedMembers($tenant, $savings, null, [$qard], 8);
    }

    private function hybrid(TenantProvisioner $provisioner): void
    {
        $admin = User::query()->create([
            'name' => 'Chioma Admin',
            'email' => 'xavier.y@example.org',
            'password' => 'Password1',
            'type' => UserType::Staff,
            'email_verified_at' => now(),
        ]);

        $tenant = $provisioner->onboard([
            'name' => 'Lagos Traders Hybrid Cooperative',
            'slug' => 'traders',
            'type' => CooperativeType::Traders->value,
            'mode' => CooperativeMode::Hybrid->value,
            'state' => 'Lagos',
            'plan' => 'enterprise',
        ], $admin);
        $provisioner->activate($tenant);
        TenantContext::set($tenant);
        $this->seedHqOfficer($tenant, 'Tunde Operations', 'hq.traders@coopos.test');

        $savings = ContributionProduct::query()->create([
            'name' => 'Weekly thrift',
            'finance_mode' => FinanceMode::Conventional,
            'frequency' => 'weekly',
            'minimum_amount_kobo' => 100000,
            'default_amount_kobo' => 500000,
        ]);

        $loanProduct = LoanProduct::query()->create([
            'name' => 'Business loan',
            'minimum_amount_kobo' => 10000000,
            'maximum_amount_kobo' => 200000000,
            'minimum_tenure_months' => 3,
            'maximum_tenure_months' => 18,
            'interest_method' => InterestMethod::Flat,
            'interest_rate_bps' => 1500,
            'repayment_frequency' => 'monthly',
        ]);

        $qard = IslamicFinancingProduct::query()->create([
            'name' => 'Qard Hasan',
            'structure' => IslamicStructure::QardHasan,
            'minimum_amount_kobo' => 1000000,
            'maximum_amount_kobo' => 20000000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'admin_fee_kobo' => 0,
            'shariah_status' => ShariahStatus::Approved,
        ]);

        $this->seedMembers($tenant, $savings, $loanProduct, [$qard], 10);
    }

    private function seedHqOfficer(Tenant $tenant, string $name, string $email): void
    {
        setPermissionsTeamId($tenant->id);
        $hq = Branch::query()->where('is_headquarters', true)->first();

        $officer = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $hq?->id,
                'type' => UserType::Staff,
                'name' => $name,
                'password' => 'Password1',
                'email_verified_at' => now(),
            ],
        );
        $officer->syncRoles(['Branch Admin']);

        if ($hq && ! $hq->admin_user_id) {
            $hq->update(['admin_user_id' => $officer->id]);
        }
    }

    private function seedMembers(
        Tenant $tenant,
        ContributionProduct $savings,
        ?LoanProduct $loanProduct,
        ?array $islamic,
        int $count,
        bool $withSatelliteBranches = false,
    ): void {
        $members = app(MemberService::class);
        $contrib = app(ContributionService::class);
        $loans = app(LoanService::class);
        $islamicService = app(IslamicFinancingService::class);

        $hq = Branch::query()->where('is_headquarters', true)->first();
        $branchIds = array_filter([$hq?->id]);

        if ($withSatelliteBranches) {
            setPermissionsTeamId($tenant->id);
            $ikeja = Branch::query()->create([
                'name' => 'Ikeja',
                'code' => 'IKEJA',
                'city' => 'Ikeja',
                'state' => $tenant->state ?: 'Lagos',
            ]);
            $abeokuta = Branch::query()->create([
                'name' => 'Abeokuta',
                'code' => 'ABK',
                'city' => 'Abeokuta',
                'state' => 'Ogun',
            ]);

            $ikejaAdmin = User::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $ikeja->id,
                'type' => UserType::Staff,
                'name' => 'Funke Ikeja',
                'email' => 'ikeja.'.$tenant->slug.'@coopos.test',
                'password' => 'Password1',
                'email_verified_at' => now(),
            ]);
            $ikejaAdmin->assignRole('Branch Admin');
            $ikeja->update(['admin_user_id' => $ikejaAdmin->id]);

            $abeokutaAdmin = User::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $abeokuta->id,
                'type' => UserType::Staff,
                'name' => 'Tunde Abeokuta',
                'email' => 'abeokuta.'.$tenant->slug.'@coopos.test',
                'password' => 'Password1',
                'email_verified_at' => now(),
            ]);
            $abeokutaAdmin->assignRole('Branch Admin');
            $abeokuta->update(['admin_user_id' => $abeokutaAdmin->id]);

            $branchIds = array_values(array_filter([$hq?->id, $ikeja->id, $abeokuta->id]));
        }

        $created = [];

        for ($i = 1; $i <= $count; $i++) {
            $member = $members->register([
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => "member{$i}.{$tenant->slug}@coopos.test",
                'password' => 'Password1',
                'phone' => '080'.fake()->numerify('########'),
                'state' => $tenant->state,
                'next_of_kin_name' => fake()->name(),
                'next_of_kin_relationship' => 'Spouse',
                'branch_id' => $branchIds[($i - 1) % max(1, count($branchIds))] ?? null,
            ]);
            $members->approve($member);
            $contrib->enroll($member, $savings, (int) $savings->default_amount_kobo);
            $contrib->recordPayment($member, $savings, (int) $savings->default_amount_kobo, 'manual', 'seed:'.$member->uuid);
            $contrib->recordPayment($member, $savings, (int) $savings->default_amount_kobo, 'manual', 'seed2:'.$member->uuid);
            $created[] = $member;
        }

        if ($loanProduct && count($created) > 2) {
            try {
                $amount = min((int) $loanProduct->minimum_amount_kobo, 2000000);
                $app = $loans->apply($created[0], $loanProduct, max($amount, 100000), 6, 'Salary support');
                $app->update(['status' => LoanStatus::Approved]);
                $loans->disburse($app->fresh());
            } catch (\InvalidArgumentException) {
                // Demo data should not fail the whole seed if a product is outside policy.
            }
        }

        if ($islamic) {
            [$qard] = $islamic;
            $financing = $islamicService->createQardHasan($created[0], $qard, $qard->minimum_amount_kobo, 6, 'Family support');
            $financing->update(['status' => LoanStatus::Approved]);
            $islamicService->disburseQardHasan($financing->fresh());

            if (isset($islamic[1])) {
                $murabaha = $islamicService->createMurabaha($created[1], $islamic[1], [
                    'asset_description' => 'Honda generator',
                    'supplier' => 'Kano Electricals',
                    'purchase_cost_kobo' => 25000000,
                    'profit_kobo' => 2500000,
                    'down_payment_kobo' => 2500000,
                    'tenure_months' => 6,
                ]);
                $murabaha->update(['status' => LoanStatus::Approved]);
                $islamicService->finalizeMurabahaSale($murabaha->fresh());
            }
        }
    }
}
