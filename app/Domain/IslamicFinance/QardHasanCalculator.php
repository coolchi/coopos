<?php

namespace App\Domain\IslamicFinance;

use InvalidArgumentException;

final class QardHasanCalculator
{
    /**
     * Qard Hasan: total repayment is principal plus permitted admin fee only. No interest.
     *
     * @return array{total_repayable_kobo: int, schedule: array<int, array<string, int|string>>}
     */
    public function schedule(int $principalKobo, int $adminFeeKobo, int $tenureMonths, string $startDate): array
    {
        if ($principalKobo <= 0 || $tenureMonths <= 0) {
            throw new InvalidArgumentException('Principal and tenure must be positive.');
        }

        $total = $principalKobo + $adminFeeKobo;
        $base = intdiv($total, $tenureMonths);
        $remainder = $total - ($base * $tenureMonths);
        $date = \Carbon\Carbon::parse($startDate);
        $schedule = [];

        for ($i = 1; $i <= $tenureMonths; $i++) {
            $amount = $base + ($i === $tenureMonths ? $remainder : 0);
            $schedule[] = [
                'installment_number' => $i,
                'due_on' => $date->copy()->addMonths($i)->toDateString(),
                'amount_due_kobo' => $amount,
            ];
        }

        return [
            'total_repayable_kobo' => $total,
            'schedule' => $schedule,
        ];
    }
}
