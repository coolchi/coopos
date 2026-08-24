<?php

namespace App\Domain\Platform;

use App\Domain\Access\StaffPermissions;
use App\Domain\Branches\BranchService;
use App\Domain\Ledger\LedgerService;
use App\Enums\CooperativeMode;
use App\Enums\CooperativeType;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\UserType;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TenantProvisioner
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function onboard(array $data, User $admin): Tenant
    {
        return DB::transaction(function () use ($data, $admin) {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'legal_name' => $data['legal_name'] ?? $data['name'],
                'registration_number' => $data['registration_number'] ?? null,
                'type' => CooperativeType::from($data['type']),
                'mode' => CooperativeMode::from($data['mode']),
                'status' => TenantStatus::Pending,
                'email' => $data['email'] ?? $admin->email,
                'phone' => $data['phone'] ?? $admin->phone,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'lga' => $data['lga'] ?? null,
            ]);

            TenantSetting::query()->create([
                'tenant_id' => $tenant->id,
                'membership_fee_kobo' => naira_to_kobo($data['membership_fee'] ?? 0),
                'fiscal_year_start_month' => (int) ($data['fiscal_year_start_month'] ?? 1),
                'fiscal_year_one_start_year' => (int) ($data['fiscal_year_one_start_year'] ?? now()->year),
            ]);

            $plan = SubscriptionPlan::query()->where('slug', $data['plan'] ?? 'starter')->first()
                ?? $this->ensurePlans()->firstWhere('slug', $data['plan'] ?? 'starter')
                ?? SubscriptionPlan::query()->orderBy('sort_order')->firstOrFail();

            Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'interval' => 'monthly',
                'trial_ends_at' => now()->addDays(config('coopos.trial_days')),
                'starts_at' => now(),
            ]);

            $admin->forceFill([
                'tenant_id' => $tenant->id,
                'type' => UserType::Staff,
            ])->save();

            $this->seedRoles($tenant);
            $this->seedDefaultWorkflows($tenant);
            $this->ledger->provisionChart($tenant);

            TenantContext::set($tenant);
            setPermissionsTeamId($tenant->id);
            app(BranchService::class)->ensureHeadquarters($tenant);
            $admin->assignRole('Administrator');

            return $tenant;
        });
    }

    public function activate(Tenant $tenant): void
    {
        $tenant->update([
            'status' => TenantStatus::Active,
            'approved_at' => now(),
            'suspended_at' => null,
        ]);
    }

    public function seedRoles(Tenant $tenant): void
    {
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);

        foreach (StaffPermissions::catalog() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (StaffPermissions::matrix() as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($perms);
        }
    }

    public function seedDefaultWorkflows(Tenant $tenant): void
    {
        TenantContext::set($tenant);

        $loanLow = $tenant->approvalWorkflows()->create([
            'name' => 'Loans up to ₦100,000',
            'applies_to' => 'loan',
            'min_amount_kobo' => 0,
            'max_amount_kobo' => 10_000_000,
        ]);
        $loanLow->steps()->createMany([
            ['tenant_id' => $tenant->id, 'position' => 1, 'name' => 'Loan Officer Review', 'role_name' => 'Loan Officer'],
            ['tenant_id' => $tenant->id, 'position' => 2, 'name' => 'Treasurer Approval', 'role_name' => 'Treasurer'],
        ]);

        $loanMid = $tenant->approvalWorkflows()->create([
            'name' => 'Loans ₦100,001 – ₦500,000',
            'applies_to' => 'loan',
            'min_amount_kobo' => 10_000_100,
            'max_amount_kobo' => 50_000_000,
        ]);
        $loanMid->steps()->createMany([
            ['tenant_id' => $tenant->id, 'position' => 1, 'name' => 'Loan Officer Review', 'role_name' => 'Loan Officer'],
            ['tenant_id' => $tenant->id, 'position' => 2, 'name' => 'Credit Committee', 'role_name' => 'Committee Member'],
        ]);

        $loanHigh = $tenant->approvalWorkflows()->create([
            'name' => 'Loans above ₦500,000',
            'applies_to' => 'loan',
            'min_amount_kobo' => 50_000_100,
            'max_amount_kobo' => null,
        ]);
        $loanHigh->steps()->createMany([
            ['tenant_id' => $tenant->id, 'position' => 1, 'name' => 'Loan Officer Review', 'role_name' => 'Loan Officer'],
            ['tenant_id' => $tenant->id, 'position' => 2, 'name' => 'Credit Committee', 'role_name' => 'Committee Member'],
            ['tenant_id' => $tenant->id, 'position' => 3, 'name' => 'Chairman Approval', 'role_name' => 'Chairman'],
        ]);

        $withdrawal = $tenant->approvalWorkflows()->create([
            'name' => 'Withdrawals',
            'applies_to' => 'withdrawal',
            'min_amount_kobo' => 0,
        ]);
        $withdrawal->steps()->createMany([
            ['tenant_id' => $tenant->id, 'position' => 1, 'name' => 'Treasurer Approval', 'role_name' => 'Treasurer'],
        ]);

        $islamic = $tenant->approvalWorkflows()->create([
            'name' => 'Loan financing',
            'applies_to' => 'islamic_financing',
            'min_amount_kobo' => 0,
        ]);
        $islamic->steps()->createMany([
            ['tenant_id' => $tenant->id, 'position' => 1, 'name' => 'Treasurer Approval', 'role_name' => 'Treasurer'],
            ['tenant_id' => $tenant->id, 'position' => 2, 'name' => 'Chairman Approval', 'role_name' => 'Chairman'],
        ]);
    }

    public function ensurePlans()
    {
        $plans = [
            ['name' => 'Starter', 'slug' => 'starter', 'description' => 'Core cooperative operations for small societies.', 'monthly_price_kobo' => 1500000, 'yearly_price_kobo' => 15000000, 'member_limit' => 200, 'features' => ['members', 'contributions', 'qard_hasan'], 'sort_order' => 1],
            ['name' => 'Professional', 'slug' => 'professional', 'description' => 'Loans, reports, and payment integrations.', 'monthly_price_kobo' => 4500000, 'yearly_price_kobo' => 45000000, 'member_limit' => 2000, 'features' => ['members', 'contributions', 'loans', 'murabaha', 'payments', 'reports'], 'sort_order' => 2],
            ['name' => 'Enterprise', 'slug' => 'enterprise', 'description' => 'Unlimited members, API, branding, and priority support.', 'monthly_price_kobo' => 12000000, 'yearly_price_kobo' => 120000000, 'member_limit' => null, 'features' => ['members', 'contributions', 'loans', 'murabaha', 'payments', 'reports', 'api', 'branding'], 'sort_order' => 3],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        return SubscriptionPlan::query()->orderBy('sort_order')->get();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'cooperative';
        $slug = $base;
        $i = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists() || in_array($slug, config('coopos.reserved_slugs'), true)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
