<?php

namespace App\Domain\Ledger;

use App\Enums\JournalStatus;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
use App\Models\Member;
use App\Models\Tenant;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class LedgerService
{
    /**
     * @param  array<int, array{account: string, debit?: int, credit?: int, member?: Member|null, memo?: string}>  $lines
     */
    public function post(
        string $description,
        array $lines,
        ?Model $source = null,
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
        ?Carbon $postedAt = null,
        ?int $branchId = null,
    ): JournalEntry {
        $tenant = TenantContext::require();

        if ($idempotencyKey) {
            $existing = JournalEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $normalized = $this->normalizeLines($tenant, $lines);
        $debit = collect($normalized)->sum('debit_kobo');
        $credit = collect($normalized)->sum('credit_kobo');

        if ($debit !== $credit || $debit === 0) {
            throw new InvalidArgumentException("Unbalanced journal: debit {$debit} credit {$credit}.");
        }

        $postedAt ??= now();
        $branchId ??= $source?->getAttribute('branch_id') ?: BranchContext::id();

        return DB::transaction(function () use ($description, $normalized, $source, $idempotencyKey, $createdBy, $postedAt, $branchId) {
            $entry = JournalEntry::query()->create([
                'reference' => 'JE-'.strtoupper(Str::ulid()),
                'description' => $description,
                'status' => JournalStatus::Posted,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'created_by' => $createdBy ?? auth()->id(),
                'idempotency_key' => $idempotencyKey,
                'posted_at' => $postedAt,
                'branch_id' => $branchId,
            ]);

            foreach ($normalized as $line) {
                $entry->lines()->create($line);
            }

            return $entry->load('lines');
        });
    }

    public function recordTransaction(
        string $type,
        int $amountKobo,
        array $journalLines,
        ?Member $member = null,
        ?Model $related = null,
        ?string $channel = null,
        ?string $idempotencyKey = null,
        array $meta = [],
        ?Carbon $postedAt = null,
    ): FinancialTransaction {
        if ($idempotencyKey) {
            $existing = FinancialTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($type, $amountKobo, $journalLines, $member, $related, $channel, $idempotencyKey, $meta, $postedAt) {
            $txn = FinancialTransaction::query()->create([
                'reference' => 'TXN-'.strtoupper(Str::ulid()),
                'member_id' => $member?->id,
                'type' => $type,
                'amount_kobo' => $amountKobo,
                'status' => TransactionStatus::Successful,
                'channel' => $channel,
                'idempotency_key' => $idempotencyKey,
                'related_type' => $related?->getMorphClass(),
                'related_id' => $related?->getKey(),
                'created_by' => auth()->id(),
                'initiated_at' => $postedAt ?? now(),
                'completed_at' => $postedAt ?? now(),
                'meta' => $meta,
            ]);

            $entry = $this->post(
                description: Str::headline($type),
                lines: $journalLines,
                source: $txn,
                idempotencyKey: $idempotencyKey ? "journal:{$idempotencyKey}" : null,
                postedAt: $postedAt,
            );

            $txn->update(['journal_entry_id' => $entry->id]);

            return $txn->fresh();
        });
    }

    public function reverseTransaction(FinancialTransaction $txn): FinancialTransaction
    {
        if ($txn->status === TransactionStatus::Reversed) {
            throw new InvalidArgumentException('This transaction has already been reversed.');
        }

        $entry = $txn->journalEntry;
        if (! $entry) {
            throw new RuntimeException('Transaction has no journal entry to reverse.');
        }

        if ($entry->status === JournalStatus::Reversed) {
            throw new InvalidArgumentException('Journal entry has already been reversed.');
        }

        return DB::transaction(function () use ($txn, $entry) {
            $entry->load(['lines.account', 'lines.member']);

            $lines = $entry->lines->map(fn ($line) => [
                'account' => $line->account->code,
                'debit' => (int) $line->credit_kobo,
                'credit' => (int) $line->debit_kobo,
                'member' => $line->member,
                'memo' => 'Reversal'.($line->memo ? ': '.$line->memo : ''),
            ])->all();

            $reversal = $this->post(
                description: 'Reversal: '.$entry->description,
                lines: $lines,
                source: $txn,
                postedAt: now(),
                branchId: $entry->branch_id,
            );

            $entry->update([
                'status' => JournalStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by_entry_id' => $reversal->id,
            ]);

            $txn->update(['status' => TransactionStatus::Reversed]);

            return $txn->fresh();
        });
    }

    public function memberSavingsBalance(Member $member): int
    {
        return $this->memberAccountBalance($member, '2100');
    }

    public function memberSharesBalance(Member $member): int
    {
        return $this->memberAccountBalance($member, '3000');
    }

    public function memberWalletBalance(Member $member): int
    {
        return $this->memberAccountBalance($member, '2110');
    }

    public function memberAccountBalance(Member $member, string $code): int
    {
        $account = $this->account($code);

        return (int) $account->lines()
            ->where('member_id', $member->id)
            ->selectRaw('COALESCE(SUM(credit_kobo) - SUM(debit_kobo), 0) as balance')
            ->value('balance');
    }

    public function accountBalance(string $code): int
    {
        $account = $this->account($code);
        $raw = (int) $account->lines()
            ->selectRaw('COALESCE(SUM(debit_kobo) - SUM(credit_kobo), 0) as balance')
            ->value('balance');

        return $account->normalCreditBalance() ? -$raw : $raw;
    }

    public function account(string $code): Account
    {
        $account = Account::query()->where('code', $code)->first();

        if (! $account) {
            throw new RuntimeException("Account {$code} is not provisioned for this cooperative.");
        }

        return $account;
    }

    public function provisionChart(Tenant $tenant): void
    {
        $wasBypassed = TenantContext::isBypassed();
        TenantContext::set($tenant);

        foreach (StandardChart::accounts() as $row) {
            $account = Account::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $row['code']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'sort_order' => $row['sort'],
                    'is_system' => true,
                ],
            );

            $updates = [];
            if ((int) $account->sort_order !== (int) $row['sort']) {
                $updates['sort_order'] = $row['sort'];
            }
            if ($account->name === 'Penalty Income' || $account->name === 'Withdrawal Fees Contra') {
                $updates['name'] = $row['name'];
            }
            if ($updates !== []) {
                $account->forceFill($updates)->save();
            }
        }

        if ($wasBypassed) {
            TenantContext::bypass();
        }
    }

    /**
     * @param  array<int, array{account: string, debit?: int, credit?: int, member?: Member|null, memo?: string}>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(Tenant $tenant, array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $debit = (int) ($line['debit'] ?? 0);
            $credit = (int) ($line['credit'] ?? 0);

            if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                throw new InvalidArgumentException('Each journal line must be either a debit or a credit.');
            }

            $account = Account::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', $line['account'])
                ->firstOrFail();

            $normalized[] = [
                'tenant_id' => $tenant->id,
                'account_id' => $account->id,
                'member_id' => $line['member']->id ?? $line['member_id'] ?? null,
                'debit_kobo' => $debit,
                'credit_kobo' => $credit,
                'memo' => $line['memo'] ?? null,
            ];
        }

        return $normalized;
    }
}
