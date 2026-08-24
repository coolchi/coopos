<?php

namespace App\Domain\IslamicFinance;

use App\Domain\Approvals\ApprovalService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ledger\WalletService;
use App\Domain\Lending\LoanEligibilityService;
use App\Domain\Notifications\Notifier;
use App\Enums\IslamicStructure;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Models\IslamicFinancingProduct;
use App\Models\Member;
use App\Models\MurabahaFinancing;
use App\Models\QardHasanFinancing;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class IslamicFinancingService
{
    public function __construct(
        private readonly QardHasanCalculator $qard,
        private readonly MurabahaCalculator $murabaha,
        private readonly LedgerService $ledger,
        private readonly ApprovalService $approvals,
        private readonly LoanEligibilityService $eligibility,
    ) {}

    public function createQardHasan(
        Member $member,
        IslamicFinancingProduct $product,
        int $principalKobo,
        int $tenureMonths,
        string $purpose = '',
    ): QardHasanFinancing {
        $this->assertIslamic($product, IslamicStructure::QardHasan);
        $this->assertMember($member);
        $this->assertBounds($product, $principalKobo, $tenureMonths);
        $this->eligibility->assert($member, $principalKobo);

        $calc = $this->qard->schedule($principalKobo, $product->admin_fee_kobo, $tenureMonths, now()->toDateString());

        return DB::transaction(function () use ($member, $product, $principalKobo, $tenureMonths, $purpose, $calc) {
            $financing = QardHasanFinancing::query()->create([
                'member_id' => $member->id,
                'islamic_financing_product_id' => $product->id,
                'account_number' => 'QH-'.strtoupper(Str::ulid()),
                'principal_kobo' => $principalKobo,
                'admin_fee_kobo' => $product->admin_fee_kobo,
                'total_repayable_kobo' => $calc['total_repayable_kobo'],
                'outstanding_kobo' => $calc['total_repayable_kobo'],
                'tenure_months' => $tenureMonths,
                'status' => LoanStatus::PendingApproval,
                'purpose' => $purpose,
            ]);

            foreach ($calc['schedule'] as $row) {
                $financing->installments()->create($row + ['status' => 'pending']);
            }

            $this->approvals->start('islamic_financing', $financing, $principalKobo);

            return $financing->load('installments');
        });
    }

    public function disburseQardHasan(QardHasanFinancing $financing): QardHasanFinancing
    {
        if (in_array($financing->status, [LoanStatus::Active, LoanStatus::Disbursed], true)) {
            return $financing;
        }

        if ($financing->status !== LoanStatus::Approved) {
            throw new InvalidArgumentException('This facility must be approved before it can be disbursed.');
        }

        $financing = DB::transaction(function () use ($financing) {
            $txn = $this->ledger->recordTransaction(
                type: 'qard_hasan_disbursement',
                amountKobo: $financing->principal_kobo,
                journalLines: [
                    ['account' => '1110', 'debit' => $financing->principal_kobo, 'member' => $financing->member, 'memo' => 'Qard Hasan principal'],
                    ['account' => '1010', 'credit' => $financing->principal_kobo, 'memo' => 'Disbursement'],
                ],
                member: $financing->member,
                related: $financing,
                channel: 'bank_transfer',
                idempotencyKey: 'qard-disburse:'.$financing->uuid,
            );

            if ($financing->admin_fee_kobo > 0) {
                $this->ledger->recordTransaction(
                    type: 'qard_hasan_admin_fee',
                    amountKobo: $financing->admin_fee_kobo,
                    journalLines: [
                        ['account' => '1110', 'debit' => $financing->admin_fee_kobo, 'member' => $financing->member, 'memo' => 'Permitted admin fee'],
                        ['account' => '4020', 'credit' => $financing->admin_fee_kobo, 'memo' => 'Service charge'],
                    ],
                    member: $financing->member,
                    related: $financing,
                    channel: 'internal',
                    idempotencyKey: 'qard-fee:'.$financing->uuid,
                );
            }

            $financing->update([
                'status' => LoanStatus::Active,
                'disbursed_on' => now()->toDateString(),
                'maturity_on' => now()->addMonths($financing->tenure_months)->toDateString(),
                'financial_transaction_id' => $txn->id,
            ]);

            return $financing->fresh('installments');
        });

        $financing->loadMissing('member');
        app(Notifier::class)->financingDisbursed($financing->member, 'Qard Hasan', $financing->principal_kobo);

        return $financing;
    }

    public function repayQardHasan(QardHasanFinancing $financing, int $amountKobo, string $channel = 'manual', ?string $idempotencyKey = null, ?CarbonInterface $postedAt = null, bool $fromWallet = false): QardHasanFinancing
    {
        return $this->repayInstallments($financing, $amountKobo, 'qard_hasan_repayment', '1110', $channel, $idempotencyKey, $postedAt, $fromWallet);
    }

    public function createMurabaha(Member $member, IslamicFinancingProduct $product, array $data): MurabahaFinancing
    {
        $this->assertIslamic($product, IslamicStructure::Murabaha);
        $this->assertMember($member);

        $cost = (int) $data['purchase_cost_kobo'];
        $profit = (int) $data['profit_kobo'];
        $down = (int) ($data['down_payment_kobo'] ?? 0);
        $tenure = (int) $data['tenure_months'];

        $this->assertBounds($product, $cost, $tenure);
        $this->eligibility->assert($member, $cost);
        $calc = $this->murabaha->schedule($cost, $profit, $down, $tenure, now()->toDateString());

        return DB::transaction(function () use ($member, $product, $data, $cost, $profit, $down, $tenure, $calc) {
            $financing = MurabahaFinancing::query()->create([
                'member_id' => $member->id,
                'islamic_financing_product_id' => $product->id,
                'account_number' => 'MR-'.strtoupper(Str::ulid()),
                'asset_description' => $data['asset_description'],
                'supplier' => $data['supplier'] ?? null,
                'purchase_cost_kobo' => $cost,
                'profit_kobo' => $profit,
                'sale_price_kobo' => $calc['sale_price_kobo'],
                'down_payment_kobo' => $down,
                'outstanding_kobo' => $calc['financed_kobo'],
                'tenure_months' => $tenure,
                'status' => LoanStatus::PendingApproval,
                'invoice_path' => $data['invoice_path'] ?? null,
                'sale_frozen' => false,
            ]);

            $financing->asset()->create([
                'tenant_id' => $financing->tenant_id,
                'name' => $data['asset_description'],
                'description' => $data['asset_description'],
                'ownership_status' => 'cooperative',
            ]);

            foreach ($calc['schedule'] as $row) {
                $financing->installments()->create($row + ['status' => 'pending']);
            }

            $this->approvals->start('islamic_financing', $financing, $calc['sale_price_kobo']);

            return $financing->load('installments', 'asset');
        });
    }

    public function finalizeMurabahaSale(MurabahaFinancing $financing): MurabahaFinancing
    {
        if ($financing->sale_frozen) {
            return $financing;
        }

        if ($financing->status !== LoanStatus::Approved) {
            throw new InvalidArgumentException('This facility must be approved before the sale can be finalized.');
        }

        $financing = DB::transaction(function () use ($financing) {
            $sale = $financing->sale_price_kobo;
            $cost = $financing->purchase_cost_kobo;
            $profit = $financing->profit_kobo;
            $down = $financing->down_payment_kobo;

            $this->ledger->recordTransaction(
                type: 'murabaha_sale',
                amountKobo: $sale,
                journalLines: array_values(array_filter([
                    $sale - $down > 0 ? ['account' => '1120', 'debit' => $sale - $down, 'member' => $financing->member, 'memo' => 'Murabaha receivable'] : null,
                    $down > 0 ? ['account' => '1010', 'debit' => $down, 'memo' => 'Down payment'] : null,
                    ['account' => '1010', 'credit' => $cost, 'memo' => 'Asset purchase cost'],
                    $profit > 0 ? ['account' => '4010', 'credit' => $profit, 'memo' => 'Disclosed Murabaha profit'] : null,
                ])),
                member: $financing->member,
                related: $financing,
                channel: 'internal',
                idempotencyKey: 'murabaha-sale:'.$financing->uuid,
            );

            $financing->asset?->update(['ownership_status' => 'member']);
            $financing->update([
                'sale_frozen' => true,
                'agreement_on' => now()->toDateString(),
                'status' => LoanStatus::Active,
                'disbursed_on' => now()->toDateString(),
            ]);

            return $financing->fresh(['installments', 'asset']);
        });

        $financing->loadMissing('member');
        app(Notifier::class)->financingDisbursed($financing->member, 'Murabaha', $financing->sale_price_kobo);

        return $financing;
    }

    public function repayMurabaha(MurabahaFinancing $financing, int $amountKobo, string $channel = 'manual', ?string $idempotencyKey = null, ?CarbonInterface $postedAt = null, bool $fromWallet = false): MurabahaFinancing
    {
        return $this->repayInstallments($financing, $amountKobo, 'murabaha_repayment', '1120', $channel, $idempotencyKey, $postedAt, $fromWallet);
    }

    private function repayInstallments(
        QardHasanFinancing|MurabahaFinancing $financing,
        int $amountKobo,
        string $type,
        string $receivableAccount,
        string $channel,
        ?string $idempotencyKey,
        ?CarbonInterface $postedAt = null,
        bool $fromWallet = false,
    ): QardHasanFinancing|MurabahaFinancing {
        if ($amountKobo <= 0) {
            throw new InvalidArgumentException('Repayment must be positive.');
        }

        if ($fromWallet) {
            app(WalletService::class)->assertSufficient($financing->member, $amountKobo);
        }

        return DB::transaction(function () use ($financing, $amountKobo, $type, $receivableAccount, $channel, $idempotencyKey, $postedAt, $fromWallet) {
            $remaining = $amountKobo;
            $paidAt = $postedAt ?? now();
            $installments = $financing->installments()->where('status', '!=', 'paid')->orderBy('installment_number')->lockForUpdate()->get();

            foreach ($installments as $installment) {
                if ($remaining <= 0) {
                    break;
                }
                $due = $installment->outstandingKobo();
                $pay = min($due, $remaining);
                $installment->increment('amount_paid_kobo', $pay);
                if ($installment->fresh()->outstandingKobo() === 0) {
                    $installment->update(['status' => 'paid', 'paid_at' => $paidAt]);
                }
                $remaining -= $pay;
            }

            $applied = $amountKobo - $remaining;

            $this->ledger->recordTransaction(
                type: $type,
                amountKobo: $applied,
                journalLines: [
                    $fromWallet
                        ? ['account' => WalletService::ACCOUNT, 'debit' => $applied, 'member' => $financing->member, 'memo' => 'Paid from wallet']
                        : ['account' => '1010', 'debit' => $applied, 'memo' => 'Loan repayment'],
                    ['account' => $receivableAccount, 'credit' => $applied, 'member' => $financing->member, 'memo' => 'Receivable reduction'],
                ],
                member: $financing->member,
                related: $financing,
                channel: $channel,
                idempotencyKey: $idempotencyKey,
                postedAt: $postedAt,
            );

            $financing->decrement('outstanding_kobo', $applied);
            $financing->refresh();

            if ($financing->outstanding_kobo <= 0) {
                $financing->update(['status' => LoanStatus::Completed, 'outstanding_kobo' => 0]);
            }

            return $financing;
        });
    }

    private function assertIslamic(IslamicFinancingProduct $product, IslamicStructure $structure): void
    {
        if ($product->structure !== $structure) {
            throw new LogicException('Wrong Islamic structure for this operation.');
        }
    }

    private function assertMember(Member $member): void
    {
        if ($member->status !== MemberStatus::Active) {
            throw new InvalidArgumentException('Only active members may apply for a loan.');
        }
    }

    private function assertBounds(IslamicFinancingProduct $product, int $amountKobo, int $tenureMonths): void
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
