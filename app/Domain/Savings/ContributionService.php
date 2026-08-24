<?php

namespace App\Domain\Savings;

use App\Domain\Ledger\LedgerService;
use App\Domain\Ledger\WalletService;
use App\Domain\Notifications\Notifier;
use App\Enums\ContributionFrequency;
use App\Enums\TransactionStatus;
use App\Models\ApprovalRequest;
use App\Models\Contribution;
use App\Models\ContributionEnrollment;
use App\Models\ContributionProduct;
use App\Models\ContributionSchedule;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\MemberBankAccount;
use App\Models\WithdrawalRequest;
use App\Support\FiscalPeriod;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ContributionService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function enroll(Member $member, ContributionProduct $product, int $amountKobo, ?string $start = null): ContributionEnrollment
    {
        if ($product->amount_is_fixed) {
            $amountKobo = (int) $product->default_amount_kobo;
        }

        if ($amountKobo < $product->minimum_amount_kobo) {
            throw new InvalidArgumentException('Amount is below the product minimum of '.money((int) $product->minimum_amount_kobo).'.');
        }

        if ($product->maximum_amount_kobo && $amountKobo > $product->maximum_amount_kobo) {
            throw new InvalidArgumentException('Amount exceeds the product maximum of '.money((int) $product->maximum_amount_kobo).'.');
        }

        $startDate = $start ? Carbon::parse($start) : now();

        $enrollment = ContributionEnrollment::query()
            ->where('member_id', $member->id)
            ->where('contribution_product_id', $product->id)
            ->where('status', 'active')
            ->first();

        $created = false;

        if ($enrollment) {
            $enrollment->update(['amount_kobo' => $amountKobo]);
        } else {
            try {
                $enrollment = ContributionEnrollment::query()->create([
                    'member_id' => $member->id,
                    'contribution_product_id' => $product->id,
                    'amount_kobo' => $amountKobo,
                    'status' => 'active',
                    'starts_on' => $startDate->toDateString(),
                ]);
                $created = true;
            } catch (UniqueConstraintViolationException) {
                $enrollment = ContributionEnrollment::query()
                    ->where('member_id', $member->id)
                    ->where('contribution_product_id', $product->id)
                    ->where('status', 'active')
                    ->firstOrFail();
                $enrollment->update(['amount_kobo' => $amountKobo]);
            }
        }

        $this->generateUpcomingSchedules($enrollment->fresh()->loadMissing('product'), 12);

        return tap($enrollment->fresh(), fn (ContributionEnrollment $row) => $row->wasRecentlyCreated = $created);
    }

    public function generateUpcomingSchedules(ContributionEnrollment $enrollment, int $count = 12): void
    {
        $enrollment->loadMissing('product');
        $due = Carbon::parse($enrollment->starts_on);

        for ($i = 0; $i < $count; $i++) {
            $dueOn = $due->toDateString();
            $schedule = ContributionSchedule::query()
                ->where('member_id', $enrollment->member_id)
                ->where('contribution_product_id', $enrollment->contribution_product_id)
                ->whereDate('due_on', $dueOn)
                ->first();

            if ($schedule) {
                if ($schedule->status !== 'paid') {
                    $schedule->update([
                        'contribution_enrollment_id' => $enrollment->id,
                        'amount_kobo' => $enrollment->amount_kobo,
                    ]);
                }
            } else {
                try {
                    ContributionSchedule::query()->create([
                        'member_id' => $enrollment->member_id,
                        'contribution_product_id' => $enrollment->contribution_product_id,
                        'contribution_enrollment_id' => $enrollment->id,
                        'due_on' => $dueOn,
                        'amount_kobo' => $enrollment->amount_kobo,
                        'status' => 'pending',
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Another request created this due date.
                }
            }

            $due = $this->nextDue($due, $enrollment->product->frequency);
        }
    }

    public function recordPayment(
        Member $member,
        ContributionProduct $product,
        int $amountKobo,
        string $channel = 'cash',
        ?string $idempotencyKey = null,
        ?int $scheduleId = null,
        ?string $paidOn = null,
        bool $skipLimits = false,
        ?string $forMonth = null,
        bool $fromWallet = false,
    ): Contribution {
        if ($amountKobo <= 0) {
            throw new InvalidArgumentException('Contribution amount must be positive.');
        }

        if (! $skipLimits) {
            if ($amountKobo < $product->minimum_amount_kobo) {
                throw new InvalidArgumentException('Amount is below the product minimum of '.money((int) $product->minimum_amount_kobo).'.');
            }
            if ($product->maximum_amount_kobo && $amountKobo > $product->maximum_amount_kobo) {
                throw new InvalidArgumentException('Amount exceeds the product maximum of '.money((int) $product->maximum_amount_kobo).'.');
            }
        }

        if ($fromWallet) {
            app(WalletService::class)->assertSufficient($member, $amountKobo);
        }

        $creditAccount = $product->bucket?->ledgerCreditAccount() ?? '2100';
        $paidOn = $paidOn ?: now()->toDateString();
        $period = $forMonth
            ? Carbon::parse($forMonth)->startOfMonth()
            : Carbon::parse($paidOn)->startOfMonth();
        $channel = $channel === 'bank_transfer' ? 'transfer' : $channel;

        $this->assertFrequencyAllowsPayment($member, $product, $period);

        $contribution = DB::transaction(function () use ($member, $product, $amountKobo, $channel, $idempotencyKey, $scheduleId, $creditAccount, $paidOn, $period, $fromWallet) {
            $monthStart = $period->toDateString();
            $monthEnd = $period->copy()->endOfMonth()->toDateString();

            $schedule = $scheduleId
                ? ContributionSchedule::query()->findOrFail($scheduleId)
                : ContributionSchedule::query()
                    ->where('member_id', $member->id)
                    ->where('contribution_product_id', $product->id)
                    ->where('status', '!=', 'paid')
                    ->whereBetween('due_on', [$monthStart, $monthEnd])
                    ->orderBy('due_on')
                    ->first();

            $txn = $this->ledger->recordTransaction(
                type: 'contribution',
                amountKobo: $amountKobo,
                journalLines: [
                    $fromWallet
                        ? ['account' => WalletService::ACCOUNT, 'debit' => $amountKobo, 'member' => $member, 'memo' => 'Paid from wallet']
                        : ['account' => '1010', 'debit' => $amountKobo, 'memo' => 'Contribution received'],
                    ['account' => $creditAccount, 'credit' => $amountKobo, 'member' => $member, 'memo' => $product->name],
                ],
                member: $member,
                related: $product,
                channel: $channel,
                idempotencyKey: $idempotencyKey,
                postedAt: Carbon::parse($paidOn)->endOfDay(),
            );

            $contribution = Contribution::query()->create([
                'member_id' => $member->id,
                'contribution_product_id' => $product->id,
                'contribution_schedule_id' => $schedule?->id,
                'amount_kobo' => $amountKobo,
                'paid_on' => $paidOn,
                'for_month' => $monthStart,
                'channel' => $channel,
                'financial_transaction_id' => $txn->id,
                'recorded_by' => auth()->id(),
            ]);

            if ($schedule) {
                $paid = $schedule->paid_kobo + $amountKobo;
                $schedule->update([
                    'paid_kobo' => $paid,
                    'status' => $paid >= $schedule->amount_kobo + $schedule->penalty_kobo ? 'paid' : 'partial',
                ]);
            }

            return $contribution;
        });

        app(Notifier::class)->contributionRecorded(
            $member,
            $amountKobo,
            $product->name,
        );

        return $contribution;
    }

    public function updatePayment(
        Contribution $contribution,
        ContributionProduct $product,
        int $amountKobo,
        string $channel = 'cash',
        ?string $paidOn = null,
        ?string $forMonth = null,
        bool $skipLimits = false,
    ): Contribution {
        if ($amountKobo <= 0) {
            throw new InvalidArgumentException('Contribution amount must be positive.');
        }

        if (! $skipLimits) {
            if ($amountKobo < $product->minimum_amount_kobo) {
                throw new InvalidArgumentException('Amount is below the product minimum of '.money((int) $product->minimum_amount_kobo).'.');
            }
            if ($product->maximum_amount_kobo && $amountKobo > $product->maximum_amount_kobo) {
                throw new InvalidArgumentException('Amount exceeds the product maximum of '.money((int) $product->maximum_amount_kobo).'.');
            }
        }

        $member = $contribution->member;
        $paidOn = $paidOn ?: $contribution->paid_on?->toDateString() ?: now()->toDateString();
        $period = $forMonth
            ? Carbon::parse($forMonth)->startOfMonth()
            : Carbon::parse($paidOn)->startOfMonth();
        $channel = $channel === 'bank_transfer' ? 'transfer' : $channel;
        $creditAccount = $product->bucket?->ledgerCreditAccount() ?? '2100';

        $this->assertFrequencyAllowsPayment($member, $product, $period, $contribution->id);

        return DB::transaction(function () use ($contribution, $member, $product, $amountKobo, $channel, $creditAccount, $paidOn, $period) {
            $this->detachFromSchedule($contribution);
            $this->reverseContributionLedger($contribution);

            $txn = $this->ledger->recordTransaction(
                type: 'contribution',
                amountKobo: $amountKobo,
                journalLines: [
                    ['account' => '1010', 'debit' => $amountKobo, 'memo' => 'Contribution received'],
                    ['account' => $creditAccount, 'credit' => $amountKobo, 'member' => $member, 'memo' => $product->name],
                ],
                member: $member,
                related: $product,
                channel: $channel,
                postedAt: Carbon::parse($paidOn)->endOfDay(),
            );

            $monthStart = $period->toDateString();
            $monthEnd = $period->copy()->endOfMonth()->toDateString();

            $schedule = ContributionSchedule::query()
                ->where('member_id', $member->id)
                ->where('contribution_product_id', $product->id)
                ->where('status', '!=', 'paid')
                ->whereBetween('due_on', [$monthStart, $monthEnd])
                ->orderBy('due_on')
                ->first();

            $contribution->update([
                'contribution_product_id' => $product->id,
                'contribution_schedule_id' => $schedule?->id,
                'amount_kobo' => $amountKobo,
                'paid_on' => $paidOn,
                'for_month' => $monthStart,
                'channel' => $channel,
                'financial_transaction_id' => $txn->id,
            ]);

            if ($schedule) {
                $paid = $schedule->paid_kobo + $amountKobo;
                $schedule->update([
                    'paid_kobo' => $paid,
                    'status' => $paid >= $schedule->amount_kobo + $schedule->penalty_kobo ? 'paid' : 'partial',
                ]);
            }

            return $contribution->fresh(['member', 'product']);
        });
    }

    public function deletePayment(Contribution $contribution): void
    {
        DB::transaction(function () use ($contribution) {
            $this->detachFromSchedule($contribution);
            $this->reverseContributionLedger($contribution);
            $contribution->delete();
        });
    }

    private function reverseContributionLedger(Contribution $contribution): void
    {
        if (! $contribution->financial_transaction_id) {
            return;
        }

        $txn = FinancialTransaction::query()->find($contribution->financial_transaction_id);
        if (! $txn || $txn->status === TransactionStatus::Reversed) {
            return;
        }

        $this->ledger->reverseTransaction($txn);
    }

    private function detachFromSchedule(Contribution $contribution): void
    {
        $schedule = $contribution->schedule;
        if (! $schedule) {
            return;
        }

        $paid = max(0, (int) $schedule->paid_kobo - (int) $contribution->amount_kobo);
        $due = (int) $schedule->amount_kobo + (int) $schedule->penalty_kobo;
        $status = match (true) {
            $paid <= 0 => $schedule->due_on && $schedule->due_on->lt(now()->startOfDay()) ? 'overdue' : 'pending',
            $paid >= $due => 'paid',
            default => 'partial',
        };

        $schedule->update([
            'paid_kobo' => $paid,
            'status' => $status,
        ]);
    }

    public function applyPenalties(): int
    {
        $count = 0;
        $overdue = ContributionSchedule::query()
            ->where('status', 'pending')
            ->whereDate('due_on', '<', now()->toDateString())
            ->with('product')
            ->get();

        foreach ($overdue as $schedule) {
            if ($schedule->product->penalties_enabled && $schedule->penalty_kobo === 0) {
                $schedule->update([
                    'penalty_kobo' => $schedule->product->penalty_amount_kobo,
                    'status' => 'overdue',
                ]);
                $count++;
            } else {
                $schedule->update(['status' => 'overdue']);
            }
        }

        return $count;
    }

    public function requestWithdrawal(
        Member $member,
        ContributionProduct $product,
        int $amountKobo,
        string $reason = '',
        ?MemberBankAccount $bank = null,
    ): WithdrawalRequest {
        $this->assertWithdrawableProduct($product);
        $this->assertSufficientSavings($member, $amountKobo);

        $request = WithdrawalRequest::query()->create([
            'member_id' => $member->id,
            'contribution_product_id' => $product->id,
            'type' => 'savings',
            'amount_kobo' => $amountKobo,
            'status' => 'pending',
            'reason' => $reason,
            'member_bank_account_id' => $bank?->id,
        ]);

        app(Notifier::class)->withdrawalRequested($member, $amountKobo);

        return $request;
    }

    public function updateWithdrawal(
        WithdrawalRequest $request,
        Member $member,
        ContributionProduct $product,
        int $amountKobo,
        string $reason = '',
        ?MemberBankAccount $bank = null,
        bool $updateBank = false,
    ): WithdrawalRequest {
        $this->assertWithdrawableProduct($product);

        return DB::transaction(function () use ($request, $member, $product, $amountKobo, $reason, $bank, $updateBank) {
            $posted = filled($request->financial_transaction_id);
            $status = $request->status ?: 'pending';

            if ($posted) {
                $this->reverseWithdrawalLedger($request);
                $request->update(['financial_transaction_id' => null]);
            }

            $this->assertSufficientSavings($member, $amountKobo);

            $payload = [
                'member_id' => $member->id,
                'contribution_product_id' => $product->id,
                'amount_kobo' => $amountKobo,
                'reason' => $reason,
            ];
            if ($updateBank) {
                $payload['member_bank_account_id'] = $bank?->id;
            }

            $request->update($payload);

            if ($posted) {
                return $this->payoutWithdrawal($request->fresh(['member', 'product', 'bankAccount']), $status === 'approved' ? 'paid' : $status);
            }

            return $request->fresh(['member', 'product', 'bankAccount']);
        });
    }

    public function resolvePayoutAccount(Member $member, ?string $bankAccountUuid, array $details = []): MemberBankAccount
    {
        if (filled($bankAccountUuid) && $bankAccountUuid !== '__new__') {
            $account = MemberBankAccount::query()
                ->where('uuid', $bankAccountUuid)
                ->where('member_id', $member->id)
                ->first();

            if (! $account) {
                throw new InvalidArgumentException('Choose a bank account that belongs to this member.');
            }

            return $account;
        }

        $bankName = trim((string) ($details['bank_name'] ?? ''));
        $number = preg_replace('/\s+/', '', (string) ($details['account_number'] ?? '')) ?? '';
        $name = trim((string) ($details['account_name'] ?? '')) ?: $member->fullName();

        if ($bankName === '' || $number === '') {
            throw new InvalidArgumentException('Select or enter the bank this payout should be sent to.');
        }

        $existing = MemberBankAccount::query()
            ->where('member_id', $member->id)
            ->where('account_number', $number)
            ->first();

        if ($existing) {
            if ($existing->bank_name !== $bankName || $existing->account_name !== $name) {
                $existing->update([
                    'bank_name' => $bankName,
                    'account_name' => $name,
                ]);
            }

            return $existing->fresh();
        }

        return MemberBankAccount::query()->create([
            'member_id' => $member->id,
            'bank_name' => $bankName,
            'account_number' => $number,
            'account_name' => $name,
            'is_primary' => ! $member->bankAccounts()->exists(),
        ]);
    }

    public function deleteWithdrawal(WithdrawalRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $this->reverseWithdrawalLedger($request);

            ApprovalRequest::query()
                ->where('approvable_type', $request->getMorphClass())
                ->where('approvable_id', $request->getKey())
                ->delete();

            $request->delete();
        });
    }

    public function approveWithdrawal(WithdrawalRequest $request): WithdrawalRequest
    {
        return $this->payoutWithdrawal($request, 'paid');
    }

    public function payoutWithdrawal(WithdrawalRequest $request, string $status = 'paid'): WithdrawalRequest
    {
        $this->assertWithdrawableProduct($request->product);

        $paid = DB::transaction(function () use ($request, $status) {
            $member = $request->member;
            $amount = $request->amount_kobo;
            $idempotencyKey = 'withdrawal:'.$request->uuid;
            $existing = FinancialTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing && $existing->status === TransactionStatus::Reversed) {
                $idempotencyKey .= ':'.strtolower((string) Str::ulid());
            }

            $txn = $this->ledger->recordTransaction(
                type: 'withdrawal',
                amountKobo: $amount,
                journalLines: [
                    ['account' => '2100', 'debit' => $amount, 'member' => $member, 'memo' => 'Savings withdrawal'],
                    ['account' => '1010', 'credit' => $amount, 'memo' => 'Bank payout'],
                ],
                member: $member,
                related: $request,
                channel: 'bank_transfer',
                idempotencyKey: $idempotencyKey,
            );

            $request->update([
                'status' => $status,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'financial_transaction_id' => $txn->id,
            ]);

            return $request->fresh(['member']);
        });

        if ($status === 'paid' && $paid->member) {
            app(Notifier::class)->withdrawalPaid($paid->member, $paid->amount_kobo);
        }

        return $paid;
    }

    private function reverseWithdrawalLedger(WithdrawalRequest $request): void
    {
        if (! $request->financial_transaction_id) {
            return;
        }

        $txn = FinancialTransaction::query()->find($request->financial_transaction_id);
        if (! $txn || $txn->status === TransactionStatus::Reversed) {
            return;
        }

        $this->ledger->reverseTransaction($txn);
    }

    private function assertWithdrawableProduct(?ContributionProduct $product): void
    {
        if (! $product?->bucket?->allowsWithdrawal()) {
            throw new InvalidArgumentException('Withdrawals are only allowed from Special Target Scheme (STS).');
        }

        if ($product->is_locked) {
            throw new InvalidArgumentException('This savings product does not allow withdrawals.');
        }

        if ($product->ends_on && $product->ends_on->isFuture()) {
            throw new InvalidArgumentException('This savings product is locked until '.$product->ends_on->toDateString().'.');
        }
    }

    private function assertSufficientSavings(Member $member, int $amountKobo): void
    {
        if ($amountKobo > $this->ledger->memberSavingsBalance($member)) {
            throw new InvalidArgumentException('Insufficient savings balance.');
        }
    }

    private function assertFrequencyAllowsPayment(
        Member $member,
        ContributionProduct $product,
        Carbon $period,
        ?int $exceptContributionId = null,
    ): void {
        $frequency = $product->frequency;

        if ($frequency === ContributionFrequency::OneTime) {
            $exists = Contribution::query()
                ->where('member_id', $member->id)
                ->where('contribution_product_id', $product->id)
                ->when($exceptContributionId, fn ($q) => $q->whereKeyNot($exceptContributionId))
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException($product->name.' is a one-time payment and has already been recorded for this member.');
            }

            return;
        }

        if ($frequency === ContributionFrequency::Yearly) {
            $fy = FiscalPeriod::forDate($period);
            $exists = Contribution::query()
                ->where('member_id', $member->id)
                ->where('contribution_product_id', $product->id)
                ->when($exceptContributionId, fn ($q) => $q->whereKeyNot($exceptContributionId))
                ->where(function ($q) use ($fy) {
                    $q->whereBetween('for_month', [$fy->start->toDateString(), $fy->end->toDateString()])
                        ->orWhere(function ($inner) use ($fy) {
                            $inner->whereNull('for_month')
                                ->whereBetween('paid_on', [$fy->start->toDateString(), $fy->end->toDateString()]);
                        });
                })
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException($product->name.' has already been paid for financial year '.$fy->label().'.');
            }
        }
    }

    private function nextDue(Carbon $current, ContributionFrequency $frequency): Carbon
    {
        return match ($frequency) {
            ContributionFrequency::Daily => $current->copy()->addDay(),
            ContributionFrequency::Weekly => $current->copy()->addWeek(),
            ContributionFrequency::Biweekly => $current->copy()->addWeeks(2),
            ContributionFrequency::Monthly => $current->copy()->addMonth(),
            ContributionFrequency::Quarterly => $current->copy()->addMonths(3),
            ContributionFrequency::Yearly => $current->copy()->addYear(),
            ContributionFrequency::OneTime, ContributionFrequency::Custom => $current->copy()->addMonth(),
        };
    }
}
