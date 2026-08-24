<?php

namespace App\Domain\Ledger;

use App\Models\FinancialTransaction;
use App\Models\Member;
use InvalidArgumentException;

class WalletService
{
    public const ACCOUNT = '2110';

    public function __construct(private readonly LedgerService $ledger) {}

    public function balance(Member $member): int
    {
        return $this->ledger->memberWalletBalance($member);
    }

    public function credit(
        Member $member,
        int $amountKobo,
        string $channel = 'transfer',
        ?string $idempotencyKey = null,
    ): FinancialTransaction {
        if ($amountKobo <= 0) {
            throw new InvalidArgumentException('Wallet credit must be positive.');
        }

        return $this->ledger->recordTransaction(
            type: 'wallet_credit',
            amountKobo: $amountKobo,
            journalLines: [
                ['account' => '1010', 'debit' => $amountKobo, 'memo' => 'Wallet funded'],
                ['account' => self::ACCOUNT, 'credit' => $amountKobo, 'member' => $member, 'memo' => 'Member wallet'],
            ],
            member: $member,
            channel: $channel,
            idempotencyKey: $idempotencyKey,
        );
    }

    public function assertSufficient(Member $member, int $amountKobo): void
    {
        if ($this->balance($member) < $amountKobo) {
            throw new InvalidArgumentException('Insufficient wallet balance. Transfer to your virtual account first.');
        }
    }
}
