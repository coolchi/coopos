<?php

namespace App\Domain\Lending;

use App\Domain\Ledger\LedgerService;
use App\Enums\ContributionBucket;
use App\Enums\ContributionFrequency;
use App\Models\Contribution;
use App\Models\ContributionProduct;
use App\Models\MeetingAttendance;
use App\Models\Member;
use App\Models\TenantSetting;
use App\Support\FiscalPeriod;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class LoanEligibilityService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @return array{passed: bool, amount_kobo: int, checks: list<array<string, mixed>>}
     */
    public function evaluate(Member $member, int $amountKobo = 0): array
    {
        $settings = tenant()?->settings;
        $checks = [];

        $savingsPercent = (int) ($settings?->loan_min_savings_percent ?? 50);
        $sharesPercent = (int) ($settings?->loan_min_shares_percent ?? 10);

        if ($amountKobo > 0 && $savingsPercent > 0) {
            $checks[] = $this->balanceCheck(
                id: 'savings',
                label: "Savings ({$savingsPercent}% of loan)",
                actualKobo: $this->ledger->memberSavingsBalance($member),
                requiredKobo: (int) ceil($amountKobo * $savingsPercent / 100),
                noun: 'savings',
            );
        }

        if ($amountKobo > 0 && $sharesPercent > 0) {
            $checks[] = $this->balanceCheck(
                id: 'shares',
                label: "Shares ({$sharesPercent}% of loan)",
                actualKobo: $this->ledger->memberSharesBalance($member),
                requiredKobo: (int) ceil($amountKobo * $sharesPercent / 100),
                noun: 'shares',
            );
        }

        foreach ($this->requiredProducts($settings) as $product) {
            $checks[] = $this->productCheck($member, $product);
        }

        if ($settings?->loan_check_attendance ?? true) {
            $max = max(1, (int) ($settings?->loan_max_absences_per_year ?? 3));
            $absences = $this->absencesThisYear($member);
            $pass = $absences < $max;
            $year = FiscalPeriod::forYear()->label();
            $checks[] = [
                'id' => 'attendance',
                'label' => "Attendance (not {$max} absent in a year)",
                'pass' => $pass,
                'skipped' => false,
                'actual' => (string) $absences,
                'required' => 'Fewer than '.$max,
                'detail' => $pass
                    ? "{$absences} absent this financial year ({$year}). Limit is {$max}."
                    : "Has {$absences} absences this financial year ({$year}). Must have fewer than {$max}.",
            ];
        }

        return [
            'passed' => collect($checks)->every(fn (array $check) => $check['pass']),
            'amount_kobo' => $amountKobo,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{passed: bool, amount_kobo: int, checks: list<array<string, mixed>>}
     */
    public function assert(Member $member, int $amountKobo): array
    {
        $result = $this->evaluate($member, $amountKobo);

        if ($result['passed']) {
            return $result;
        }

        $failed = collect($result['checks'])
            ->where('pass', false)
            ->pluck('detail')
            ->filter()
            ->implode(' ');

        throw new InvalidArgumentException($failed !== '' ? $failed : 'Member does not meet loan conditions.');
    }

    /**
     * @return array{id: string, label: string, pass: bool, skipped: bool, actual: string, required: string, detail: string}
     */
    private function balanceCheck(string $id, string $label, int $actualKobo, int $requiredKobo, string $noun): array
    {
        $pass = $actualKobo >= $requiredKobo;

        return [
            'id' => $id,
            'label' => $label,
            'pass' => $pass,
            'skipped' => false,
            'actual' => money($actualKobo),
            'required' => money($requiredKobo),
            'detail' => $pass
                ? 'Has '.money($actualKobo).' in '.$noun.' (needs '.money($requiredKobo).').'
                : 'Needs '.money($requiredKobo).' in '.$noun.'. Currently '.money($actualKobo).'.',
        ];
    }

    /**
     * Contribution products this cooperative requires members to have paid before a loan.
     *
     * @return Collection<int, ContributionProduct>
     */
    public function requiredProducts(?TenantSetting $settings): Collection
    {
        $query = ContributionProduct::query()->where('is_active', true);
        $saved = $settings?->loan_required_product_uuids;

        if (is_array($saved)) {
            if ($saved === []) {
                return collect();
            }

            return $query->whereIn('uuid', $saved)
                ->get()
                ->sortBy(fn (ContributionProduct $product) => sprintf('%02d-%s', $product->bucket?->sortOrder() ?? 99, mb_strtolower($product->name)))
                ->values();
        }

        return $query->whereIn('bucket', $this->legacyRequiredBuckets($settings))
            ->get()
            ->sortBy(fn (ContributionProduct $product) => sprintf('%02d-%s', $product->bucket?->sortOrder() ?? 99, mb_strtolower($product->name)))
            ->values();
    }

    /**
     * @return list<string>
     */
    private function legacyRequiredBuckets(?TenantSetting $settings): array
    {
        $buckets = [];

        if ($settings?->loan_require_registration ?? true) {
            $buckets[] = ContributionBucket::RegistrationFee->value;
        }
        if ($settings?->loan_require_foundation ?? true) {
            $buckets[] = ContributionBucket::FoundationFee->value;
        }
        if ($settings?->loan_require_agm ?? true) {
            $buckets[] = ContributionBucket::Agm->value;
        }
        if ($settings?->loan_require_dev_levy ?? true) {
            $buckets[] = ContributionBucket::DevelopmentLevy->value;
        }

        return $buckets;
    }

    /**
     * @return array{id: string, label: string, pass: bool, skipped: bool, actual: string, required: string, detail: string}
     */
    private function productCheck(Member $member, ContributionProduct $product): array
    {
        $paid = $this->productIsPaid($member, $product);
        $period = $product->frequency === ContributionFrequency::OneTime
            ? 'Paid'
            : 'Paid this financial year';

        return [
            'id' => 'product:'.$product->uuid,
            'label' => $product->name,
            'pass' => $paid,
            'skipped' => false,
            'actual' => $paid ? 'Paid' : 'Unpaid',
            'required' => $period,
            'detail' => $paid
                ? $product->name.' has been paid.'
                : $product->name.' is outstanding. '.$period.' is required.',
        ];
    }

    private function productIsPaid(Member $member, ContributionProduct $product): bool
    {
        $query = Contribution::query()
            ->where('member_id', $member->id)
            ->where('contribution_product_id', $product->id);

        $frequency = $product->frequency;

        if ($frequency === ContributionFrequency::OneTime) {
            return $query->exists();
        }

        $fy = FiscalPeriod::forYear();

        return $query->where(function ($q) use ($fy) {
            $q->whereBetween('for_month', [$fy->start->toDateString(), $fy->end->toDateString()])
                ->orWhere(function ($inner) use ($fy) {
                    $inner->whereNull('for_month')
                        ->whereBetween('paid_on', [$fy->start->toDateString(), $fy->end->toDateString()]);
                });
        })->exists();
    }

    private function absencesThisYear(Member $member): int
    {
        $fy = FiscalPeriod::forYear();

        return (int) MeetingAttendance::query()
            ->where('member_id', $member->id)
            ->where('status', 'absent')
            ->whereHas('meeting', fn ($q) => $q->whereBetween('held_on', [$fy->start->toDateString(), $fy->end->toDateString()]))
            ->count();
    }
}
