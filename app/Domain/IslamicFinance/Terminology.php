<?php

namespace App\Domain\IslamicFinance;

use App\Enums\FinanceMode;
use App\Models\Tenant;

final class Terminology
{
    public static function for(Tenant $tenant): array
    {
        return [
            'loan' => 'Loan',
            'loans' => 'Loans',
            'interest' => $tenant->offersIslamic() && ! $tenant->offersConventional()
                ? 'Profit'
                : ($tenant->offersIslamic() && $tenant->offersConventional() ? 'Interest / Profit' : 'Interest'),
            'repayment' => 'Repayment',
            'disbursement' => 'Disbursement',
            'financing' => 'Loan',
            'outstanding' => 'Loans outstanding',
        ];
    }

    public static function label(FinanceMode $mode, string $conventional, string $islamic): string
    {
        return $mode === FinanceMode::Islamic ? $islamic : $conventional;
    }
}
