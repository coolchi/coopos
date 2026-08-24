<?php

namespace Database\Seeders;

use App\Domain\Platform\TenantProvisioner;
use App\Enums\CooperativeMode;
use App\Enums\CooperativeType;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\UserType;
use App\Models\Announcement;
use App\Models\Invoice;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class PlatformDemoSeeder extends Seeder
{
    public function run(): void
    {
        TenantContext::bypass();

        PlatformSetting::putMany(PlatformSetting::defaults());

        User::query()->create([
            'name' => 'Funke Support',
            'email' => 'samuel.w@example.com',
            'password' => 'Password1',
            'type' => UserType::Platform,
            'is_platform_admin' => false,
            'email_verified_at' => now(),
        ]);

        User::query()->create([
            'name' => 'Tunde Billing',
            'email' => 'julia.r@example.org',
            'password' => 'Password1',
            'type' => UserType::Platform,
            'is_platform_admin' => true,
            'email_verified_at' => now(),
        ]);

        $provisioner = app(TenantProvisioner::class);

        $extras = [
            [
                'name' => 'Abuja Civil Service Cooperative',
                'slug' => 'abuja',
                'type' => CooperativeType::Staff->value,
                'mode' => CooperativeMode::General->value,
                'state' => 'FCT',
                'plan' => 'starter',
                'status' => TenantStatus::Pending,
                'billing' => SubscriptionStatus::Trialing,
                'months_ago' => 0,
            ],
            [
                'name' => 'Kano Market Women Cooperative',
                'slug' => 'kano',
                'type' => CooperativeType::Traders->value,
                'mode' => CooperativeMode::General->value,
                'state' => 'Kano',
                'plan' => 'starter',
                'status' => TenantStatus::Active,
                'billing' => SubscriptionStatus::Active,
                'months_ago' => 3,
            ],
            [
                'name' => 'Port Harcourt Farmers Multipurpose',
                'slug' => 'phfarmers',
                'type' => CooperativeType::Farmers->value,
                'mode' => CooperativeMode::General->value,
                'state' => 'Rivers',
                'plan' => 'professional',
                'status' => TenantStatus::Suspended,
                'billing' => SubscriptionStatus::PastDue,
                'months_ago' => 8,
            ],
            [
                'name' => 'Enugu Teachers Thrift',
                'slug' => 'enugu',
                'type' => CooperativeType::Staff->value,
                'mode' => CooperativeMode::General->value,
                'state' => 'Enugu',
                'plan' => 'professional',
                'status' => TenantStatus::Pending,
                'billing' => SubscriptionStatus::Trialing,
                'months_ago' => 0,
            ],
            [
                'name' => 'Kaduna Medical Cooperative',
                'slug' => 'kaduna',
                'type' => CooperativeType::Staff->value,
                'mode' => CooperativeMode::Hybrid->value,
                'state' => 'Kaduna',
                'plan' => 'enterprise',
                'status' => TenantStatus::Active,
                'billing' => SubscriptionStatus::PastDue,
                'months_ago' => 6,
            ],
            [
                'name' => 'Ibadan Artisans Cooperative',
                'slug' => 'ibadan',
                'type' => CooperativeType::Community->value,
                'mode' => CooperativeMode::General->value,
                'state' => 'Oyo',
                'plan' => 'professional',
                'status' => TenantStatus::Active,
                'billing' => SubscriptionStatus::Active,
                'months_ago' => 5,
            ],
            [
                'name' => 'Sokoto Ummah Cooperative',
                'slug' => 'sokoto',
                'type' => CooperativeType::Islamic->value,
                'mode' => CooperativeMode::Islamic->value,
                'state' => 'Sokoto',
                'plan' => 'professional',
                'status' => TenantStatus::Active,
                'billing' => SubscriptionStatus::Active,
                'months_ago' => 2,
            ],
        ];

        foreach ($extras as $i => $row) {
            $admin = User::query()->create([
                'name' => fake()->name(),
                'email' => $row['slug'].'@coopos.test',
                'password' => 'Password1',
                'type' => UserType::Staff,
                'email_verified_at' => now()->subMonths($row['months_ago']),
            ]);

            $tenant = $provisioner->onboard([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'type' => $row['type'],
                'mode' => $row['mode'],
                'state' => $row['state'],
                'plan' => $row['plan'],
                'email' => $admin->email,
                'phone' => '0803'.str_pad((string) (2000000 + $i), 7, '0', STR_PAD_LEFT),
                'city' => $row['state'] === 'FCT' ? 'Abuja' : $row['state'],
            ], $admin);

            if ($row['status'] === TenantStatus::Active) {
                $provisioner->activate($tenant);
            } elseif ($row['status'] === TenantStatus::Suspended) {
                $provisioner->activate($tenant);
                $tenant->update([
                    'status' => TenantStatus::Suspended,
                    'suspended_at' => now()->subWeeks(2),
                    'suspension_reason' => 'Outstanding SaaS invoice beyond grace period.',
                ]);
            }

            $createdAt = now()->subMonths($row['months_ago'])->subDays($i + 2);
            $tenant->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
            $tenant->subscription?->update([
                'status' => $row['billing'],
                'starts_at' => $createdAt,
                'trial_ends_at' => $row['billing'] === SubscriptionStatus::Trialing ? now()->addDays(10) : $createdAt->copy()->addDays(14),
            ]);
        }

        $this->seedBillingAndOps();
    }

    private function seedBillingAndOps(): void
    {
        TenantContext::bypass();

        $invoiceNo = 10420;
        $support = User::query()->where('email', 'samuel.w@example.com')->first();

        Tenant::query()->with('subscription.plan')->get()->each(function (Tenant $tenant) use (&$invoiceNo, $support) {
            $plan = $tenant->subscription?->plan;
            if (! $plan) {
                return;
            }

            $price = (int) $plan->monthly_price_kobo;
            $ageMonths = max(1, (int) $tenant->created_at?->diffInMonths(now()) + 1);

            for ($m = $ageMonths; $m >= 1; $m--) {
                $date = now()->subMonths($m)->startOfMonth()->addDays(3);
                $status = $tenant->subscription?->status === SubscriptionStatus::PastDue && $m === 1
                    ? 'past_due'
                    : 'paid';

                Invoice::query()->create([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $tenant->subscription?->id,
                    'number' => 'INV-2026-'.str_pad((string) $invoiceNo++, 4, '0', STR_PAD_LEFT),
                    'amount_kobo' => $price,
                    'status' => $status,
                    'due_at' => $date->copy()->addDays(7),
                    'paid_at' => $status === 'paid' ? $date->copy()->addDays(2) : null,
                    'provider' => $status === 'paid' ? 'paystack' : null,
                    'provider_reference' => $status === 'paid' ? 'PSK-'.strtoupper(fake()->bothify('??####')) : null,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }

            if (in_array($tenant->slug, ['barakah', 'ijebu', 'traders', 'kaduna', 'phfarmers'], true)) {
                $admin = $tenant->users()->first();
                $ticket = SupportTicket::query()->create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $admin?->id ?? $support?->id,
                    'category' => fake()->randomElement(['billing', 'onboarding', 'islamic', 'technical']),
                    'priority' => fake()->randomElement(['low', 'normal', 'high']),
                    'subject' => fake()->randomElement([
                        'Need help mapping old contribution cards',
                        'Paystack settlement not showing in ledger',
                        'Request to raise member cap this quarter',
                        'Shariah board wants Murabaha report export',
                        'Cannot impersonate treasurer during audit',
                    ]),
                    'status' => fake()->randomElement(['open', 'pending', 'resolved']),
                    'assigned_to' => $support?->id,
                    'created_at' => now()->subDays(fake()->numberBetween(1, 20)),
                ]);
                $ticket->messages()->create([
                    'user_id' => $admin?->id ?? $support?->id,
                    'body' => 'We need platform help with this before the next AGM.',
                ]);
            }
        });

        $hero = User::query()->where('email', 'nathan.k@example.net')->first();

        Announcement::query()->create([
            'title' => 'Ramadan financing freeze window',
            'body' => 'Islamic cooperatives can now schedule a Murabaha origination pause from the product screen. Conventional books are unaffected.',
            'is_platform' => true,
            'created_by' => $hero?->id,
            'published_at' => now()->subDays(4),
        ]);

        Announcement::query()->create([
            'title' => 'Subdomains are live',
            'body' => 'Each cooperative now has a workspace host, for example barakah.coopos.test. Bookmarks to /{slug}/dashboard on coopos.test redirect automatically.',
            'is_platform' => true,
            'created_by' => $hero?->id,
            'published_at' => now()->subDay(),
        ]);

        foreach (['ijebu', 'barakah', 'traders'] as $i => $slug) {
            Tenant::query()->where('slug', $slug)->first()?->subscription?->update([
                'status' => SubscriptionStatus::Active,
                'starts_at' => now()->subMonths(4 - $i),
                'trial_ends_at' => now()->subMonths(4 - $i)->addDays(14),
            ]);
        }
    }
}
