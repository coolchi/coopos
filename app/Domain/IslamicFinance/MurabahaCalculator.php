<?php

namespace App\Domain\IslamicFinance;

use InvalidArgumentException;

final class MurabahaCalculator
{
    /**
     * Murabaha: sale_price = purchase_cost + disclosed profit. Never an interest rate.
     *
     * @return array{sale_price_kobo: int, financed_kobo: int, schedule: array<int, array<string, int|string>>}
     */
    public function schedule(
        int $purchaseCostKobo,
        int $profitKobo,
        int $downPaymentKobo,
        int $tenureMonths,
        string $startDate,
    ): array {
        if ($purchaseCostKobo <= 0 || $tenureMonths <= 0) {
            throw new InvalidArgumentException('Purchase cost and tenure must be positive.');
        }

        if ($profitKobo < 0 || $downPaymentKobo < 0) {
            throw new InvalidArgumentException('Profit and down payment cannot be negative.');
        }

        $salePrice = $purchaseCostKobo + $profitKobo;

        if ($downPaymentKobo > $salePrice) {
            throw new InvalidArgumentException('Down payment cannot exceed the sale price.');
        }

        $financed = $salePrice - $downPaymentKobo;
        $base = $tenureMonths > 0 ? intdiv($financed, $tenureMonths) : $financed;
        $remainder = $financed - ($base * $tenureMonths);
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
            'sale_price_kobo' => $salePrice,
            'financed_kobo' => $financed,
            'schedule' => $schedule,
        ];
    }
}
