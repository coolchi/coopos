<?php

namespace App\Domain\Lending;

use App\Domain\Approvals\ApprovalService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ledger\WalletService;
use App\Domain\Notifications\Notifier;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use App\Models\Member;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LoanService
{
    public function __construct(
        private readonly InterestCalculator $calculator,
        private readonly LedgerService $ledger,
        private readonly ApprovalService $approvals,
        private readonly LoanEligibilityService $eligibility,
    ) {}

    public function preview(LoanProduct $product, int $amountKobo, int $tenureMonths): array
    {
        $this->assertProductBounds($product, $amountKobo, $tenureMonths);
        $fees = $product->processing_fee_kobo + $product->insurance_fee_kobo;

        return $this->calculator->schedule(
            $amountKobo,
            $product->interest_rate_bps,
            $tenureMonths,
            $product->interest_method,
            now()->toDateString(),
            $fees,
        );
    }

    public function apply(Member $member, LoanProduct $product, int $amountKobo, int $tenureMonths, string $purpose = '', array $guarantorIds = []): LoanApplication
    {
        $conditions = $this->assertEligibility($member, $product, $amountKobo, $tenureMonths);
        $preview = $this->preview($product, $amountKobo, $tenureMonths);

        return DB::transaction(function () use ($member, $product, $amountKobo, $tenureMonths, $purpose, $guarantorIds, $preview, $conditions) {
            $application = LoanApplication::query()->create([
                'member_id' => $member->id,
                'loan_product_id' => $product->id,
                'amount_kobo' => $amountKobo,
                'tenure_months' => $tenureMonths,
                'estimated_repayment_kobo' => $preview['total_repayable_kobo'],
                'purpose' => $purpose,
                'status' => $product->guarantors_required > 0 ? LoanStatus::PendingGuarantor : LoanStatus::PendingApproval,
                'eligibility_snapshot' => [
                    'checked_at' => now()->toIso8601String(),
                    'passed' => $conditions['passed'],
                    'checks' => $conditions['checks'],
                ],
                'submitted_at' => now(),
            ]);

            foreach ($guarantorIds as $id) {
                $this->addGuarantor($application, Member::query()->findOrFail($id), $amountKobo);
            }

            if ($application->status === LoanStatus::PendingApproval) {
                $this->approvals->start('loan', $application, $amountKobo);
            }

            return $application->load('guarantors');
        });
    }

    public function addGuarantor(LoanApplication $application, Member $guarantor, int $exposureKobo): LoanGuarantor
    {
        if ($guarantor->id === $application->member_id) {
            throw new InvalidArgumentException('A member cannot guarantee their own loan.');
        }

        if (! $guarantor->canTransact()) {
            throw new InvalidArgumentException('Guarantor must be an active member.');
        }

        $cap = tenant()?->settings?->guarantor_exposure_cap_kobo
            ?? config('coopos.guarantor_exposure_cap_kobo');

        $current = (int) LoanGuarantor::query()
            ->where('guarantor_member_id', $guarantor->id)
            ->where('status', 'accepted')
            ->sum('exposure_kobo');

        if ($current + $exposureKobo > $cap) {
            throw new InvalidArgumentException('Guarantor exposure would exceed the configured limit.');
        }

        $row = LoanGuarantor::query()->create([
            'loan_application_id' => $application->id,
            'guarantor_member_id' => $guarantor->id,
            'exposure_kobo' => $exposureKobo,
            'status' => 'pending',
        ]);

        $application->loadMissing('member');
        app(Notifier::class)->guaranteeRequested($guarantor, $application->member, $exposureKobo);

        return $row;
    }

    public function respondToGuarantee(LoanGuarantor $guarantor, bool $accept, ?string $note = null): void
    {
        $guarantor->update([
            'status' => $accept ? 'accepted' : 'rejected',
            'responded_at' => now(),
            'response_note' => $note,
        ]);

        $application = $guarantor->application()->with('product', 'guarantors')->firstOrFail();
        $required = $application->product->guarantors_required;
        $accepted = $application->guarantors->where('status', 'accepted')->count();

        if (! $accept) {
            $application->update(['status' => LoanStatus::PendingGuarantor]);

            return;
        }

        if ($accepted >= $required) {
            $application->update(['status' => LoanStatus::PendingApproval]);
            $this->approvals->start('loan', $application, $application->amount_kobo);
        }
    }

    public function disburse(LoanApplication $application): Loan
    {
        $application->load('product', 'member');

        if (! in_array($application->status, [LoanStatus::Approved, LoanStatus::PendingApproval], true)
            && $application->status !== LoanStatus::Approved) {
            // allow after approval workflow completed
        }

        $product = $application->product;
        $preview = $this->preview($product, $application->amount_kobo, $application->tenure_months);

        $loan = DB::transaction(function () use ($application, $product, $preview) {
            $loan = Loan::query()->create([
                'member_id' => $application->member_id,
                'loan_product_id' => $product->id,
                'loan_application_id' => $application->id,
                'account_number' => 'LN-'.strtoupper(Str::ulid()),
                'principal_kobo' => $application->amount_kobo,
                'interest_kobo' => $preview['interest_kobo'],
                'fees_kobo' => $product->processing_fee_kobo + $product->insurance_fee_kobo,
                'total_repayable_kobo' => $preview['total_repayable_kobo'],
                'outstanding_kobo' => $preview['total_repayable_kobo'],
                'tenure_months' => $application->tenure_months,
                'interest_method' => $product->interest_method,
                'interest_rate_bps' => $product->interest_rate_bps,
                'repayment_frequency' => $product->repayment_frequency,
                'status' => LoanStatus::Active,
                'disbursed_on' => now()->toDateString(),
                'maturity_on' => now()->addMonths($application->tenure_months)->toDateString(),
            ]);

            foreach ($preview['schedule'] as $row) {
                $loan->repayments()->create($row + ['status' => 'pending']);
            }

            $txn = $this->ledger->recordTransaction(
                type: 'loan_disbursement',
                amountKobo: $application->amount_kobo,
                journalLines: [
                    ['account' => '1100', 'debit' => $application->amount_kobo, 'member' => $application->member, 'memo' => 'Loan principal'],
                    ['account' => '1010', 'credit' => $application->amount_kobo, 'memo' => 'Disbursement'],
                ],
                member: $application->member,
                related: $loan,
                channel: 'bank_transfer',
                idempotencyKey: 'loan-disburse:'.$application->uuid,
            );

            $loan->update(['financial_transaction_id' => $txn->id]);
            $application->update(['status' => LoanStatus::Disbursed]);

            return $loan->load('repayments');
        });

        $loan->loadMissing('member');
        app(Notifier::class)->financingDisbursed($loan->member, 'loan', $loan->principal_kobo);

        return $loan;
    }

    public function repay(Loan $loan, int $amountKobo, string $channel = 'manual', ?string $idempotencyKey = null, ?CarbonInterface $postedAt = null, bool $fromWallet = false): Loan
    {
        if ($amountKobo <= 0) {
            throw new InvalidArgumentException('Repayment must be positive.');
        }

        if ($fromWallet) {
            app(WalletService::class)->assertSufficient($loan->member, $amountKobo);
        }

        return DB::transaction(function () use ($loan, $amountKobo, $channel, $idempotencyKey, $postedAt, $fromWallet) {
            $remaining = $amountKobo;
            $principalPaid = 0;
            $interestPaid = 0;
            $paidAt = $postedAt ?? now();

            $installments = $loan->repayments()->where('status', '!=', 'paid')->orderBy('installment_number')->lockForUpdate()->get();

            foreach ($installments as $installment) {
                if ($remaining <= 0) {
                    break;
                }

                $due = $installment->outstandingKobo();
                $pay = min($due, $remaining);
                $interestShare = min($installment->interest_due_kobo, $pay);
                $principalShare = $pay - $interestShare;

                $installment->increment('amount_paid_kobo', $pay);
                if ($installment->fresh()->outstandingKobo() === 0) {
                    $installment->update(['status' => 'paid', 'paid_at' => $paidAt]);
                }

                $remaining -= $pay;
                $principalPaid += $principalShare;
                $interestPaid += $interestShare;
            }

            $applied = $amountKobo - $remaining;

            $this->ledger->recordTransaction(
                type: 'loan_repayment',
                amountKobo: $applied,
                journalLines: array_values(array_filter([
                    $fromWallet
                        ? ['account' => WalletService::ACCOUNT, 'debit' => $applied, 'member' => $loan->member, 'memo' => 'Paid from wallet']
                        : ['account' => '1010', 'debit' => $applied, 'memo' => 'Loan repayment received'],
                    $principalPaid > 0 ? ['account' => '1100', 'credit' => $principalPaid, 'member' => $loan->member, 'memo' => 'Principal'] : null,
                    $interestPaid > 0 ? ['account' => '4000', 'credit' => $interestPaid, 'memo' => 'Loan interest income'] : null,
                ])),
                member: $loan->member,
                related: $loan,
                channel: $channel,
                idempotencyKey: $idempotencyKey,
                postedAt: $postedAt,
            );

            $loan->decrement('outstanding_kobo', $applied);
            $loan->refresh();

            if ($loan->outstanding_kobo <= 0) {
                $loan->update(['status' => LoanStatus::Completed, 'outstanding_kobo' => 0]);
            }

            return $loan;
        });
    }

    public function assertEligibility(Member $member, LoanProduct $product, int $amountKobo, int $tenureMonths): array
    {
        if ($member->status !== MemberStatus::Active) {
            throw new InvalidArgumentException('Only active members may apply for a loan.');
        }

        $this->assertProductBounds($product, $amountKobo, $tenureMonths);

        $activeLoans = $member->loans()->whereIn('status', [LoanStatus::Active, LoanStatus::Disbursed, LoanStatus::Defaulted])->count();
        $max = tenant()?->settings?->max_active_loans ?? 1;

        if ($activeLoans >= $max) {
            throw new InvalidArgumentException('Member already has the maximum number of active loans.');
        }

        $conditions = $this->eligibility->assert($member, $amountKobo);

        $savings = $this->ledger->memberSavingsBalance($member);
        $ratioBps = tenant()?->settings?->max_debt_to_savings_bps ?? 20000;

        if ($savings > 0 && (int) round($amountKobo / $savings * 10000) > $ratioBps) {
            throw new InvalidArgumentException('Requested amount exceeds the debt-to-savings policy.');
        }

        return $conditions;
    }

    private function assertProductBounds(LoanProduct $product, int $amountKobo, int $tenureMonths): void
    {
        if ($amountKobo < (int) $product->minimum_amount_kobo) {
            throw new InvalidArgumentException('Amount must be at least '.money((int) $product->minimum_amount_kobo).'.');
        }

        if ($product->maximum_amount_kobo && $amountKobo > $product->maximum_amount_kobo) {
            throw new InvalidArgumentException('Amount cannot exceed '.money((int) $product->maximum_amount_kobo).'.');
        }

        if ($tenureMonths < (int) $product->minimum_tenure_months) {
            throw new InvalidArgumentException('Tenure must be at least '.$product->minimum_tenure_months.' months.');
        }

        if ($product->maximum_tenure_months && $tenureMonths > $product->maximum_tenure_months) {
            throw new InvalidArgumentException('Tenure cannot exceed '.$product->maximum_tenure_months.' months.');
        }
    }
}
