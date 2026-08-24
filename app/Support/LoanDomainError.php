<?php

namespace App\Support;

final class LoanDomainError
{
    public static function field(string $message, string $default = 'amount'): string
    {
        return match (true) {
            str_contains($message, 'Tenure'), str_contains($message, 'tenure') => 'tenure_months',
            str_contains($message, 'Only active members') => 'member_uuid',
            str_contains($message, 'savings'),
            str_contains($message, 'shares'),
            str_contains($message, 'outstanding'),
            str_contains($message, 'absent'),
            str_contains($message, 'attendance'),
            str_contains($message, 'loan condition') => 'eligibility',
            default => $default,
        };
    }
}
