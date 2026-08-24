<?php

namespace App\Domain\Lending;

use App\Enums\InterestMethod;
use InvalidArgumentException;

final class InterestCalculator
{
    /**
     * Conventional interest only. Never use for Islamic products.
     *
     * @return array{interest_kobo: int, total_repayable_kobo: int, schedule: array<int, array<string, int|string>>}
     */
    public function schedule(
        int $principalKobo,
        int $rateBps,
        int $tenureMonths,
        InterestMethod $method,
        string $startDate,
        int $feesKobo = 0,
    ): array {
        if ($principalKobo <= 0 || $tenureMonths <= 0) {
            throw new InvalidArgumentException('Principal and tenure must be positive.');
        }

        return match ($method) {
            InterestMethod::Flat => $this->flat($principalKobo, $rateBps, $tenureMonths, $startDate, $feesKobo),
            InterestMethod::ReducingBalance, InterestMethod::DecliningBalance => $this->reducing(
                $principalKobo, $rateBps, $tenureMonths, $startDate, $feesKobo
            ),
            InterestMethod::Custom => $this->flat($principalKobo, $rateBps, $tenureMonths, $startDate, $feesKobo),
        };
    }

    private function flat(int $principal, int $rateBps, int $months, string $start, int $fees): array
    {
        $interest = (int) round($principal * $rateBps / 10000 * $months / 12);
        $total = $principal + $interest + $fees;
        $base = intdiv($total, $months);
        $remainder = $total - ($base * $months);
        $principalBase = intdiv($principal, $months);
        $principalRemainder = $principal - ($principalBase * $months);
        $interestBase = intdiv($interest, $months);
        $interestRemainder = $interest - ($interestBase * $months);

        $schedule = [];
        $date = \Carbon\Carbon::parse($start);

        for ($i = 1; $i <= $months; $i++) {
            $due = $base + ($i === $months ? $remainder : 0);
            $p = $principalBase + ($i === $months ? $principalRemainder : 0);
            $int = $interestBase + ($i === $months ? $interestRemainder : 0);
            $feeShare = $due - $p - $int;

            $schedule[] = [
                'installment_number' => $i,
                'due_on' => $date->copy()->addMonths($i)->toDateString(),
                'principal_due_kobo' => $p,
                'interest_due_kobo' => $int,
                'fees_due_kobo' => max(0, $feeShare),
                'amount_due_kobo' => $due,
            ];
        }

        return [
            'interest_kobo' => $interest,
            'total_repayable_kobo' => $total,
            'schedule' => $schedule,
        ];
    }

    private function reducing(int $principal, int $rateBps, int $months, string $start, int $fees): array
    {
        $monthlyRate = $rateBps / 10000 / 12;
        $payment = $monthlyRate > 0
            ? (int) round($principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1))
            : (int) round($principal / $months);

        $balance = $principal;
        $interestTotal = 0;
        $schedule = [];
        $date = \Carbon\Carbon::parse($start);
        $feeShare = intdiv($fees, $months);
        $feeRemainder = $fees - ($feeShare * $months);

        for ($i = 1; $i <= $months; $i++) {
            $interest = (int) round($balance * $monthlyRate);
            $principalDue = $i === $months ? $balance : min($balance, $payment - $interest);
            $fee = $feeShare + ($i === $months ? $feeRemainder : 0);
            $amount = $principalDue + $interest + $fee;
            $interestTotal += $interest;
            $balance -= $principalDue;

            $schedule[] = [
                'installment_number' => $i,
                'due_on' => $date->copy()->addMonths($i)->toDateString(),
                'principal_due_kobo' => $principalDue,
                'interest_due_kobo' => $interest,
                'fees_due_kobo' => $fee,
                'amount_due_kobo' => $amount,
            ];
        }

        return [
            'interest_kobo' => $interestTotal,
            'total_repayable_kobo' => $principal + $interestTotal + $fees,
            'schedule' => $schedule,
        ];
    }
}
