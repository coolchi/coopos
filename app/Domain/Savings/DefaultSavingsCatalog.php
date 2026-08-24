<?php

namespace App\Domain\Savings;

use App\Enums\ContributionBucket;
use App\Enums\ContributionFrequency;
use App\Enums\FinanceMode;
use App\Models\ContributionProduct;
use App\Models\Tenant;

class DefaultSavingsCatalog
{
    /**
     * @return list<ContributionProduct>
     */
    public function ensure(?Tenant $tenant = null): array
    {
        $tenant ??= tenant_or_fail();
        $mode = $tenant->offersIslamic() && ! $tenant->offersConventional()
            ? FinanceMode::Islamic
            : FinanceMode::Conventional;

        $created = [];
        foreach ($this->defaults() as $row) {
            $existing = ContributionProduct::query()
                ->where('bucket', $row['bucket']->value)
                ->first();

            if (! $existing && $row['bucket'] === ContributionBucket::Savings) {
                $existing = ContributionProduct::query()
                    ->where(function ($q) {
                        $q->where('name', 'like', '%saving%')
                            ->orWhere('name', 'like', '%thrift%');
                    })
                    ->first();
                if ($existing) {
                    $existing->update(['bucket' => ContributionBucket::Savings]);
                }
            }

            if (! $existing && $row['bucket'] === ContributionBucket::Mds) {
                $existing = ContributionProduct::query()
                    ->where(function ($q) {
                        $q->where('name', 'MDS')
                            ->orWhere('name', 'like', '%monthly deposit%');
                    })
                    ->first();
                if ($existing) {
                    $existing->update(['bucket' => ContributionBucket::Mds]);
                }
            }

            if ($existing && $row['bucket'] === ContributionBucket::Mds && $existing->is_required) {
                $existing->update(['is_required' => false]);
            }

            if ($existing) {
                $created[] = $existing;
                continue;
            }

            $created[] = ContributionProduct::query()->create([
                'name' => $row['name'],
                'bucket' => $row['bucket'],
                'finance_mode' => $mode,
                'frequency' => $row['frequency'],
                'minimum_amount_kobo' => 0,
                'default_amount_kobo' => $row['default'] ?? 0,
                'is_active' => true,
                'is_required' => $row['required'] ?? false,
                'is_locked' => $row['locked'] ?? true,
                'amount_is_fixed' => $row['fixed'] ?? false,
            ]);
        }

        return $created;
    }

    /**
     * Align existing catalog products to current cooperative rules.
     */
    public function syncRules(?Tenant $tenant = null): void
    {
        $tenant ??= tenant_or_fail();

        foreach ($this->defaults() as $row) {
            ContributionProduct::query()
                ->where('bucket', $row['bucket']->value)
                ->update([
                    'frequency' => $row['frequency']->value,
                    'is_required' => $row['required'] ?? false,
                    'is_locked' => $row['locked'] ?? true,
                    'amount_is_fixed' => $row['fixed'] ?? false,
                    'minimum_amount_kobo' => 0,
                    'maximum_amount_kobo' => null,
                ]);
        }
    }

    /**
     * @return list<array{name: string, bucket: ContributionBucket, frequency: ContributionFrequency, default?: int, required?: bool, locked?: bool}>
     */
    private function defaults(): array
    {
        return [
            [
                'name' => 'Share',
                'bucket' => ContributionBucket::Share,
                'frequency' => ContributionFrequency::Monthly,
                'default' => 0,
                'required' => true,
                'locked' => true,
            ],
            [
                'name' => 'Savings',
                'bucket' => ContributionBucket::Savings,
                'frequency' => ContributionFrequency::Monthly,
                'default' => 500000,
                'required' => true,
                'locked' => true,
            ],
            [
                'name' => 'MDS',
                'bucket' => ContributionBucket::Mds,
                'frequency' => ContributionFrequency::Monthly,
                'default' => 0,
                'required' => false,
                'locked' => true,
            ],
            [
                'name' => 'Development levy',
                'bucket' => ContributionBucket::DevelopmentLevy,
                'frequency' => ContributionFrequency::Yearly,
                'default' => 0,
                'required' => true,
                'locked' => true,
            ],
            [
                'name' => 'Insurance',
                'bucket' => ContributionBucket::Insurance,
                'frequency' => ContributionFrequency::OneTime,
                'default' => 0,
                'required' => true,
                'locked' => true,
            ],
            [
                'name' => 'Special target scheme',
                'bucket' => ContributionBucket::SpecialTarget,
                'frequency' => ContributionFrequency::Monthly,
                'default' => 0,
                'required' => false,
                'locked' => false,
            ],
            [
                'name' => 'AGM',
                'bucket' => ContributionBucket::Agm,
                'frequency' => ContributionFrequency::Yearly,
                'default' => 0,
                'required' => true,
                'locked' => true,
            ],
            [
                'name' => 'Project',
                'bucket' => ContributionBucket::Project,
                'frequency' => ContributionFrequency::Monthly,
                'default' => 0,
                'required' => false,
                'locked' => true,
            ],
            [
                'name' => 'Foundation fee',
                'bucket' => ContributionBucket::FoundationFee,
                'frequency' => ContributionFrequency::OneTime,
                'default' => 0,
                'required' => true,
                'locked' => true,
            ],
            [
                'name' => 'Registration fee',
                'bucket' => ContributionBucket::RegistrationFee,
                'frequency' => ContributionFrequency::OneTime,
                'default' => 0,
                'required' => true,
                'locked' => true,
                'fixed' => true,
            ],
        ];
    }
}
