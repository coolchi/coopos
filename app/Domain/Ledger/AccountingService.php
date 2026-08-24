<?php

namespace App\Domain\Ledger;

use App\Enums\AccountType;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\AccountingVoucher;
use App\Models\Branch;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function ensureChart(): void
    {
        $this->ledger->provisionChart(TenantContext::require());
    }

    /**
     * @param  array{code: string, name: string, type: string, description?: string|null, sort_order?: int}  $data
     */
    public function createAccount(array $data): Account
    {
        $this->ensureChart();

        return Account::query()->create([
            'code' => trim($data['code']),
            'name' => trim($data['name']),
            'type' => AccountType::from($data['type']),
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 500),
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{name?: string, description?: string|null, sort_order?: int, type?: string}  $data
     */
    public function updateAccount(Account $account, array $data): Account
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? $account->name)),
            'description' => $data['description'] ?? $account->description,
            'sort_order' => (int) ($data['sort_order'] ?? $account->sort_order),
        ];

        if (! $account->is_system && isset($data['type'])) {
            $payload['type'] = AccountType::from($data['type']);
        }

        $account->update($payload);

        return $account->fresh();
    }

    public function toggleAccount(Account $account): Account
    {
        $account->update(['is_active' => ! $account->is_active]);

        return $account->fresh();
    }

    public function deleteAccount(Account $account): void
    {
        if ($account->is_system) {
            throw new InvalidArgumentException('System accounts cannot be deleted.');
        }

        if ($account->lines()->exists()) {
            throw new InvalidArgumentException('This account has journal activity. Deactivate it instead.');
        }

        $account->delete();
    }

    /**
     * @param  array{type: string, account_uuid: string, contra_account_uuid: string, amount: float|int|string, occurred_on: string, description: string, payee?: string|null, branch_uuid?: string|null}  $data
     */
    public function recordVoucher(array $data): AccountingVoucher
    {
        $this->ensureChart();

        $type = VoucherType::from($data['type']);
        $account = Account::query()->where('uuid', $data['account_uuid'])->firstOrFail();
        $contra = Account::query()->where('uuid', $data['contra_account_uuid'])->firstOrFail();
        $amount = naira_to_kobo($data['amount']);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        if (! $account->is_active || ! $contra->is_active) {
            throw new InvalidArgumentException('Both accounts must be active.');
        }

        $expected = $type === VoucherType::Expense ? AccountType::Expense : AccountType::Income;
        if ($account->type !== $expected) {
            throw new InvalidArgumentException('Choose a '.$expected->value.' account for this voucher.');
        }

        if ($contra->type !== AccountType::Asset) {
            throw new InvalidArgumentException('Cash or bank must be an asset account.');
        }

        $occurredOn = Carbon::parse($data['occurred_on'])->startOfDay();
        $branch = null;
        if (! empty($data['branch_uuid']) && ! BranchContext::isConstrained()) {
            $branch = Branch::query()->where('uuid', $data['branch_uuid'])->first();
        }

        return DB::transaction(function () use ($type, $account, $contra, $amount, $occurredOn, $data, $branch) {
            $this->backfillLegacyVoucherReferences();

            $voucher = AccountingVoucher::query()->create([
                'type' => $type,
                'reference' => $this->nextVoucherReference($type),
                'account_id' => $account->id,
                'contra_account_id' => $contra->id,
                'amount_kobo' => $amount,
                'occurred_on' => $occurredOn->toDateString(),
                'payee' => $data['payee'] ?? null,
                'description' => trim($data['description']),
                'created_by' => auth()->id(),
                'branch_id' => $branch?->id ?? BranchContext::id(),
            ]);

            $lines = $type === VoucherType::Expense
                ? [
                    ['account' => $account->code, 'debit' => $amount, 'memo' => $voucher->description],
                    ['account' => $contra->code, 'credit' => $amount, 'memo' => $voucher->payee ?: $voucher->description],
                ]
                : [
                    ['account' => $contra->code, 'debit' => $amount, 'memo' => $voucher->payee ?: $voucher->description],
                    ['account' => $account->code, 'credit' => $amount, 'memo' => $voucher->description],
                ];

            $entry = $this->ledger->post(
                description: $voucher->description,
                lines: $lines,
                source: $voucher,
                postedAt: $occurredOn->copy()->endOfDay(),
                branchId: $voucher->branch_id,
            );

            $voucher->update(['journal_entry_id' => $entry->id]);

            return $voucher->fresh(['account', 'contraAccount', 'journalEntry']);
        });
    }

    public function backfillLegacyVoucherReferences(): void
    {
        if (! AccountingVoucher::query()->where('reference', 'like', 'VCH-%')->exists()) {
            return;
        }

        $run = function () {
            foreach (VoucherType::cases() as $type) {
                $next = $this->nextVoucherSequence($type);
                AccountingVoucher::query()
                    ->where('type', $type)
                    ->where('reference', 'like', 'VCH-%')
                    ->orderBy('id')
                    ->each(function (AccountingVoucher $voucher) use ($type, &$next) {
                        $voucher->update([
                            'reference' => $type->prefix().'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT),
                        ]);
                        $next++;
                    });
            }
        };

        if (DB::transactionLevel() > 0) {
            $run();

            return;
        }

        DB::transaction($run);
    }

    private function nextVoucherReference(VoucherType $type): string
    {
        return $type->prefix().'-'.str_pad((string) $this->nextVoucherSequence($type), 5, '0', STR_PAD_LEFT);
    }

    private function nextVoucherSequence(VoucherType $type): int
    {
        $max = 0;
        AccountingVoucher::query()
            ->where('type', $type)
            ->where('reference', 'like', $type->prefix().'-%')
            ->lockForUpdate()
            ->pluck('reference')
            ->each(function (string $reference) use (&$max) {
                if (preg_match('/-(\d+)$/', $reference, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            });

        return $max + 1;
    }

    public function accountHasActivity(Account $account): bool
    {
        return $account->lines()->exists();
    }
}
