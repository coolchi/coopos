<?php

namespace App\Domain\Demo;

use App\Domain\IslamicFinance\IslamicFinancingService;
use App\Domain\Ledger\AccountingService;
use App\Domain\Lending\LoanService;
use App\Domain\Membership\MemberService;
use App\Domain\Notifications\Notifier;
use App\Domain\Savings\ContributionService;
use App\Domain\Savings\DefaultSavingsCatalog;
use App\Enums\AppMode;
use App\Enums\ContributionBucket;
use App\Enums\ContributionFrequency;
use App\Enums\InterestMethod;
use App\Enums\IslamicStructure;
use App\Enums\LoanStatus;
use App\Enums\ShariahStatus;
use App\Enums\VoucherType;
use App\Models\Account;
use App\Models\AccountingVoucher;
use App\Models\Announcement;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\Contribution;
use App\Models\ContributionEnrollment;
use App\Models\ContributionProduct;
use App\Models\ContributionSchedule;
use App\Models\DemoSeedRecord;
use App\Models\FinancialTransaction;
use App\Models\IslamicFinancingProduct;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Member;
use App\Models\MemberBankAccount;
use App\Models\MurabahaFinancing;
use App\Models\MurabahaInstallment;
use App\Models\QardHasanFinancing;
use App\Models\QardHasanInstallment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\NigerianNames;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DemoDataService
{
    public const FEATURES = [
        'members' => [
            'label' => 'Members',
            'description' => 'Nigerian member records with portal logins, next of kin, and bank accounts.',
        ],
        'savings' => [
            'label' => 'Savings & contributions',
            'description' => 'Mandates and a few months of share, savings, and STS payments.',
        ],
        'withdrawals' => [
            'label' => 'Withdrawals',
            'description' => 'Pending and paid Special Target Scheme payouts.',
        ],
        'loans' => [
            'label' => 'Loans',
            'description' => 'Conventional applications, disbursements, and a repayment.',
        ],
        'islamic' => [
            'label' => 'Islamic finance',
            'description' => 'Qard Hasan and Murabaha facilities with disclosed profit.',
        ],
        'accounting' => [
            'label' => 'Expenses & income',
            'description' => 'Office vouchers posted to the ledger.',
        ],
        'attendance' => [
            'label' => 'Attendance',
            'description' => 'Monthly and AGM meetings with a marked roll.',
        ],
        'announcements' => [
            'label' => 'Announcements',
            'description' => 'Published notices for members and staff.',
        ],
    ];

    public function __construct(
        private readonly MemberService $members,
        private readonly ContributionService $contributions,
        private readonly LoanService $loans,
        private readonly IslamicFinancingService $islamic,
        private readonly AccountingService $accounting,
        private readonly DefaultSavingsCatalog $catalog,
    ) {}

    /**
     * @return list<array{key: string, label: string, description: string, available: bool, count: int}>
     */
    public function features(?Tenant $tenant = null): array
    {
        $tenant ??= TenantContext::require();
        $counts = $this->counts();

        return collect(self::FEATURES)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'available' => $this->featureAvailable($tenant, $key),
                'count' => $counts[$key] ?? 0,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return DemoSeedRecord::query()
            ->selectRaw('feature, count(*) as aggregate')
            ->groupBy('feature')
            ->pluck('aggregate', 'feature')
            ->all();
    }

    public function seededTotal(): int
    {
        return (int) DemoSeedRecord::query()->count();
    }

    /**
     * @param  list<string>  $features
     * @return array<string, int>
     */
    public function seed(array $features): array
    {
        $this->assertDemo();
        $keys = $this->expandSeed($this->normalize($features));

        if ($keys === []) {
            throw new InvalidArgumentException('Choose at least one module to seed.');
        }

        $before = $this->counts();

        Notifier::mute(function () use ($keys) {
            DB::transaction(function () use ($keys) {
                foreach ($keys as $key) {
                    match ($key) {
                        'members' => $this->seedMembers(),
                        'savings' => $this->seedSavings(),
                        'withdrawals' => $this->seedWithdrawals(),
                        'loans' => $this->seedLoans(),
                        'islamic' => $this->seedIslamic(),
                        'accounting' => $this->seedAccounting(),
                        'attendance' => $this->seedAttendance(),
                        'announcements' => $this->seedAnnouncements(),
                        default => null,
                    };
                }
            });
        });

        $after = $this->counts();
        $created = [];
        foreach ($keys as $key) {
            $created[$key] = max(0, ($after[$key] ?? 0) - ($before[$key] ?? 0));
        }

        if (($created['announcements'] ?? 0) > 0) {
            Announcement::query()
                ->where('tenant_id', TenantContext::id())
                ->whereNotNull('published_at')
                ->latest('id')
                ->limit(3)
                ->get()
                ->each(fn (Announcement $announcement) => app(Notifier::class)->announcementPublished($announcement));
        }

        return $created;
    }

    /**
     * @param  list<string>  $features
     * @return array<string, int>
     */
    public function reset(array $features): array
    {
        $this->assertDemo();
        $keys = $this->expandReset($this->normalize($features));

        if ($keys === []) {
            throw new InvalidArgumentException('Choose at least one module to clear.');
        }

        $removed = [];

        DB::transaction(function () use ($keys, &$removed) {
            foreach ($keys as $key) {
                $removed[$key] = $this->resetFeature($key);
            }
        });

        return $removed;
    }

    public function assertDemo(): void
    {
        $mode = TenantContext::require()->settings?->appMode() ?? AppMode::Demo;

        if (! $mode->allowsSeeding()) {
            throw new InvalidArgumentException('Seeding is disabled while the cooperative is in Live mode. Switch to Demo first.');
        }
    }

    /**
     * @param  list<string>  $features
     * @return list<string>
     */
    private function normalize(array $features): array
    {
        $tenant = TenantContext::require();

        return collect($features)
            ->filter(fn ($key) => is_string($key) && isset(self::FEATURES[$key]))
            ->filter(fn (string $key) => $this->featureAvailable($tenant, $key))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $features
     * @return list<string>
     */
    private function expandSeed(array $features): array
    {
        $keys = $features;
        if (array_intersect($features, ['savings', 'withdrawals', 'loans', 'islamic', 'attendance']) && ! in_array('members', $keys, true)) {
            $keys[] = 'members';
        }
        if (array_intersect($features, ['withdrawals', 'loans']) && ! in_array('savings', $keys, true)) {
            $keys[] = 'savings';
        }

        $order = array_keys(self::FEATURES);

        return array_values(array_filter($order, fn (string $key) => in_array($key, $keys, true)));
    }

    /**
     * @param  list<string>  $features
     * @return list<string>
     */
    private function expandReset(array $features): array
    {
        $keys = $features;
        if (in_array('members', $keys, true)) {
            $keys = array_merge($keys, ['savings', 'withdrawals', 'loans', 'islamic', 'attendance']);
        }
        if (in_array('savings', $keys, true)) {
            $keys[] = 'withdrawals';
        }

        $order = array_reverse(array_keys(self::FEATURES));

        return array_values(array_filter($order, fn (string $key) => in_array($key, array_unique($keys), true)));
    }

    private function featureAvailable(Tenant $tenant, string $key): bool
    {
        return match ($key) {
            'loans' => $tenant->offersConventional(),
            'islamic' => $tenant->offersIslamic(),
            default => true,
        };
    }

    private function seedMembers(): void
    {
        $tenant = TenantContext::require();
        $slug = $tenant->slug;
        $existing = Member::query()->where('email', 'like', "demo.{$slug}.%@coopos.test")->count();
        $start = $existing + 1;
        $end = $start + 11;

        for ($i = $start; $i <= $end; $i++) {
            $person = NigerianNames::person($i);
            $email = "demo.{$slug}.{$i}@coopos.test";
            $member = $this->members->register([
                'first_name' => $person['first_name'],
                'last_name' => $person['last_name'],
                'gender' => $person['gender'],
                'email' => $email,
                'password' => 'Password1',
                'phone' => $person['phone'],
                'occupation' => $person['occupation'],
                'state' => $tenant->state,
                'next_of_kin_name' => NigerianNames::person($i + 7)['first_name'].' '.$person['last_name'],
                'next_of_kin_relationship' => $person['kin_relationship'],
                'next_of_kin_phone' => NigerianNames::phone($i + 50),
            ]);
            $this->members->approve($member);
            $this->track('members', $member);

            if ($member->user) {
                $this->track('members', $member->user);
            }

            $bank = MemberBankAccount::query()->create([
                'member_id' => $member->id,
                'bank_name' => $person['bank'],
                'account_number' => NigerianNames::accountNumber($i),
                'account_name' => $member->fullName(),
                'is_primary' => true,
                'is_verified' => true,
                'verified_at' => now(),
            ]);
            $this->track('members', $bank);
        }
    }

    private function seedSavings(): void
    {
        $this->catalog->ensure();
        $members = $this->demoMembers();
        if ($members->isEmpty()) {
            return;
        }

        $savings = $this->savingsProduct();
        $sts = $this->stsProduct();

        foreach ($members as $index => $member) {
            if ($savings) {
                $amount = $this->contributionAmount($savings, 2_000_000);
                $enrollment = $this->contributions->enroll($member, $savings, $amount);
                $this->track('savings', $enrollment);
                $this->payMonths($member, $savings, $amount, 4, $index);
            }

            if ($sts) {
                $stsAmount = $this->contributionAmount($sts, 1_000_000);
                $enrollment = $this->contributions->enroll($member, $sts, $stsAmount);
                $this->track('savings', $enrollment);
                $this->payMonths($member, $sts, $stsAmount, 3, $index);
            }
        }
    }

    private function seedWithdrawals(): void
    {
        $members = $this->demoMembers();
        $sts = $this->stsProduct();
        if (! $sts || $members->count() < 2) {
            return;
        }

        $paid = $members[0];
        $pending = $members[1];
        $bank = $paid->bankAccounts()->first();

        try {
            $request = $this->contributions->requestWithdrawal($paid, $sts, 200_000, 'School fees', $bank);
            $this->track('withdrawals', $request);
            $paidOut = $this->contributions->approveWithdrawal($request->fresh());
            $this->trackIf($paidOut->financial_transaction_id, 'withdrawals', FinancialTransaction::class);
        } catch (InvalidArgumentException) {
            // Balance or product rules may block a payout in a thin book.
        }

        try {
            $pendingRequest = $this->contributions->requestWithdrawal($pending, $sts, 100_000, 'Medical bills', $pending->bankAccounts()->first());
            $this->track('withdrawals', $pendingRequest);
        } catch (InvalidArgumentException) {
            //
        }
    }

    private function seedLoans(): void
    {
        $members = $this->demoMembers();
        if ($members->count() < 2) {
            return;
        }

        $product = $this->loanProduct();
        $borrower = $members[0];
        $amount = $this->boundedAmount($product->minimum_amount_kobo, $product->maximum_amount_kobo, 5_000_000);

        try {
            $application = $this->loans->apply($borrower, $product, $amount, min(6, (int) $product->maximum_tenure_months), 'Household support');
            $application->update(['status' => LoanStatus::Approved]);
            $this->track('loans', $application);
            $loan = $this->loans->disburse($application->fresh());
            $this->track('loans', $loan);
            $this->trackIf($loan->financial_transaction_id, 'loans', FinancialTransaction::class);

            $firstDue = (int) $loan->repayments()->orderBy('installment_number')->value('amount_due_kobo');
            if ($firstDue > 0) {
                $this->loans->repay($loan->fresh(), min($firstDue, 100_000), 'manual', 'demo-loan-repay:'.$loan->uuid);
            }
        } catch (InvalidArgumentException) {
            //
        }
    }

    private function seedIslamic(): void
    {
        $members = $this->demoMembers();
        if ($members->isEmpty()) {
            return;
        }

        $qardProduct = $this->qardProduct();
        $murabahaProduct = $this->murabahaProduct();

        try {
            $qard = $this->islamic->createQardHasan(
                $members[0],
                $qardProduct,
                $this->boundedAmount($qardProduct->minimum_amount_kobo, $qardProduct->maximum_amount_kobo, 2_000_000),
                min(6, (int) $qardProduct->maximum_tenure_months),
                'Family support',
            );
            $qard->update(['status' => LoanStatus::Approved]);
            $this->track('islamic', $qard);
            $disbursed = $this->islamic->disburseQardHasan($qard->fresh());
            $this->trackIf($disbursed->financial_transaction_id, 'islamic', FinancialTransaction::class);
        } catch (InvalidArgumentException) {
            //
        }

        if ($members->count() < 2) {
            return;
        }

        try {
            $cost = $this->boundedAmount($murabahaProduct->minimum_amount_kobo, $murabahaProduct->maximum_amount_kobo, 8_000_000);
            $profit = (int) round($cost * 0.1);
            $murabaha = $this->islamic->createMurabaha($members[1], $murabahaProduct, [
                'asset_description' => 'Honda generator',
                'supplier' => 'Kano Electricals',
                'purchase_cost_kobo' => $cost,
                'profit_kobo' => $profit,
                'down_payment_kobo' => (int) round($cost * 0.1),
                'tenure_months' => min(6, (int) $murabahaProduct->maximum_tenure_months),
            ]);
            $murabaha->update(['status' => LoanStatus::Approved]);
            $this->track('islamic', $murabaha);
            $final = $this->islamic->finalizeMurabahaSale($murabaha->fresh());
            $this->trackIf($final->financial_transaction_id, 'islamic', FinancialTransaction::class);
        } catch (InvalidArgumentException) {
            //
        }
    }

    private function seedAccounting(): void
    {
        $this->accounting->ensureChart();
        $cash = Account::query()->where('code', '1010')->first();
        $rent = Account::query()->where('code', '5010')->first();
        $salary = Account::query()->where('code', '5030')->first();
        $donations = Account::query()->where('code', '4170')->first();
        $other = Account::query()->where('code', '4210')->first();

        if (! $cash) {
            return;
        }

        $vouchers = [
            [$rent, VoucherType::Expense, 150_000, 'Alhaji Musa Properties', 'Office rent for the month'],
            [$salary, VoucherType::Expense, 85_000, 'Secretariat staff', 'Monthly secretariat allowance'],
            [$donations, VoucherType::Income, 50_000, 'Ibadan Market Women', 'Ramadan sadaqah received'],
            [$other, VoucherType::Income, 25_000, 'GTBank', 'Account interest / other income'],
        ];

        foreach ($vouchers as $i => [$account, $type, $naira, $payee, $description]) {
            if (! $account) {
                continue;
            }
            try {
                $voucher = $this->accounting->recordVoucher([
                    'type' => $type->value,
                    'account_uuid' => $account->uuid,
                    'contra_account_uuid' => $cash->uuid,
                    'amount' => $naira,
                    'occurred_on' => now()->subDays(3 + $i)->toDateString(),
                    'payee' => $payee,
                    'description' => $description,
                ]);
                $this->track('accounting', $voucher);
                $this->trackIf($voucher->journal_entry_id, 'accounting', JournalEntry::class);
            } catch (InvalidArgumentException) {
                //
            }
        }
    }

    private function seedAttendance(): void
    {
        $members = Member::query()->where('status', 'active')->get();
        if ($members->isEmpty()) {
            $members = $this->demoMembers();
        }
        if ($members->isEmpty()) {
            return;
        }

        foreach ([
            ['Monthly meeting', 'monthly', 12],
            ['Emergency committee', 'emergency', 5],
            ['Annual General Meeting', 'agm', 40],
        ] as [$title, $kind, $daysAgo]) {
            $meeting = Meeting::query()->create([
                'title' => $title,
                'held_on' => now()->subDays($daysAgo)->toDateString(),
                'kind' => $kind,
                'notes' => 'Demo roll generated for training.',
            ]);
            $this->track('attendance', $meeting);

            foreach ($members as $i => $member) {
                $present = $i % 7 !== 0;
                MeetingAttendance::query()->create([
                    'meeting_id' => $meeting->id,
                    'member_id' => $member->id,
                    'present' => $present,
                    'status' => $present ? 'present' : ($i % 3 === 0 ? 'permitted' : 'absent'),
                ]);
            }
        }
    }

    private function seedAnnouncements(): void
    {
        $userId = auth()->id();
        $rows = [
            ['AGM holds on Saturday', 'All members should attend the Annual General Meeting at the secretariat by 10:00am. Bring your membership card.', 'all', true],
            ['Savings window reminder', 'Monthly savings for this month close on the 25th. Pay into your cooperative account or at the desk.', 'active', false],
            ['Office closed for Sallah', 'The secretariat will be closed on Sallah day. Emergency withdrawals resume the next working day.', 'all', false],
        ];

        foreach ($rows as [$title, $body, $audience, $pinned]) {
            $announcement = Announcement::query()->create([
                'tenant_id' => TenantContext::id(),
                'title' => $title,
                'body' => $body,
                'audience' => $audience,
                'is_pinned' => $pinned,
                'is_platform' => false,
                'created_by' => $userId,
                'published_at' => now()->subHours(2),
            ]);
            $this->track('announcements', $announcement);
        }
    }

    private function resetFeature(string $feature): int
    {
        $records = DemoSeedRecord::query()
            ->where('feature', $feature)
            ->orderByDesc('id')
            ->get();

        foreach ($records as $record) {
            $this->purgeTracked($record);
            $record->delete();
        }

        if ($feature === 'attendance') {
            MeetingAttendance::query()
                ->whereIn('meeting_id', Meeting::query()->whereIn('id', $this->ids($records, Meeting::class))->select('id'))
                ->delete();
        }

        return $records->count();
    }

    private function purgeTracked(DemoSeedRecord $record): void
    {
        $id = (int) $record->model_id;
        $type = $record->model_type;

        match ($type) {
            (new Contribution)->getMorphClass(), Contribution::class => $this->purgeContribution($id),
            (new ContributionEnrollment)->getMorphClass(), ContributionEnrollment::class => $this->purgeEnrollment($id),
            (new WithdrawalRequest)->getMorphClass(), WithdrawalRequest::class => $this->purgeWithdrawal($id),
            (new Loan)->getMorphClass(), Loan::class => $this->purgeLoan($id),
            (new LoanApplication)->getMorphClass(), LoanApplication::class => $this->purgeLoanApplication($id),
            (new QardHasanFinancing)->getMorphClass(), QardHasanFinancing::class => $this->purgeQard($id),
            (new MurabahaFinancing)->getMorphClass(), MurabahaFinancing::class => $this->purgeMurabaha($id),
            (new AccountingVoucher)->getMorphClass(), AccountingVoucher::class => $this->purgeVoucher($id),
            (new JournalEntry)->getMorphClass(), JournalEntry::class => $this->purgeJournal($id),
            (new FinancialTransaction)->getMorphClass(), FinancialTransaction::class => $this->purgeTransaction($id),
            (new Meeting)->getMorphClass(), Meeting::class => $this->purgeMeeting($id),
            (new Announcement)->getMorphClass(), Announcement::class => Announcement::query()->whereKey($id)->delete(),
            (new MemberBankAccount)->getMorphClass(), MemberBankAccount::class => MemberBankAccount::query()->whereKey($id)->delete(),
            (new Member)->getMorphClass(), Member::class => $this->purgeMember($id),
            (new User)->getMorphClass(), User::class => $this->purgeUser($id),
            (new LoanProduct)->getMorphClass(), LoanProduct::class => LoanProduct::withTrashed()->whereKey($id)->forceDelete(),
            (new IslamicFinancingProduct)->getMorphClass(), IslamicFinancingProduct::class => IslamicFinancingProduct::withTrashed()->whereKey($id)->forceDelete(),
            default => $this->deleteByType($type, $id),
        };
    }

    private function deleteByType(string $type, int $id): void
    {
        if (! class_exists($type)) {
            return;
        }

        $model = new $type;
        if ($model instanceof Model) {
            $model->newQuery()->whereKey($id)->delete();
        }
    }

    private function purgeContribution(int $id): void
    {
        $row = Contribution::query()->find($id);
        if (! $row) {
            return;
        }
        $this->purgeTransaction((int) $row->financial_transaction_id);
        $row->delete();
    }

    private function purgeEnrollment(int $id): void
    {
        ContributionSchedule::query()->where('contribution_enrollment_id', $id)->delete();
        ContributionEnrollment::query()->whereKey($id)->delete();
    }

    private function purgeWithdrawal(int $id): void
    {
        $row = WithdrawalRequest::query()->find($id);
        if (! $row) {
            return;
        }
        $this->purgeApprovals($row);
        $this->purgeTransaction((int) $row->financial_transaction_id);
        $row->delete();
    }

    private function purgeLoan(int $id): void
    {
        $loan = Loan::query()->find($id);
        if (! $loan) {
            return;
        }

        FinancialTransaction::query()
            ->where('related_type', $loan->getMorphClass())
            ->where('related_id', $loan->id)
            ->get()
            ->each(fn (FinancialTransaction $txn) => $this->purgeTransaction($txn->id));

        $this->purgeTransaction((int) $loan->financial_transaction_id);
        LoanRepayment::query()->where('loan_id', $loan->id)->delete();
        $loan->delete();
    }

    private function purgeLoanApplication(int $id): void
    {
        $application = LoanApplication::query()->find($id);
        if (! $application) {
            return;
        }
        $this->purgeApprovals($application);
        LoanGuarantor::query()->where('loan_application_id', $application->id)->delete();
        $application->delete();
    }

    private function purgeQard(int $id): void
    {
        $row = QardHasanFinancing::query()->find($id);
        if (! $row) {
            return;
        }
        $this->purgeApprovals($row);
        FinancialTransaction::query()
            ->where('related_type', $row->getMorphClass())
            ->where('related_id', $row->id)
            ->get()
            ->each(fn (FinancialTransaction $txn) => $this->purgeTransaction($txn->id));
        $this->purgeTransaction((int) $row->financial_transaction_id);
        QardHasanInstallment::query()->where('qard_hasan_financing_id', $row->id)->delete();
        $row->delete();
    }

    private function purgeMurabaha(int $id): void
    {
        $row = MurabahaFinancing::query()->find($id);
        if (! $row) {
            return;
        }
        $this->purgeApprovals($row);
        FinancialTransaction::query()
            ->where('related_type', $row->getMorphClass())
            ->where('related_id', $row->id)
            ->get()
            ->each(fn (FinancialTransaction $txn) => $this->purgeTransaction($txn->id));
        $this->purgeTransaction((int) $row->financial_transaction_id);
        MurabahaInstallment::query()->where('murabaha_financing_id', $row->id)->delete();
        $row->asset()->delete();
        $row->delete();
    }

    private function purgeVoucher(int $id): void
    {
        $voucher = AccountingVoucher::query()->find($id);
        if (! $voucher) {
            return;
        }
        $this->purgeJournal((int) $voucher->journal_entry_id);
        $voucher->delete();
    }

    private function purgeMeeting(int $id): void
    {
        MeetingAttendance::query()->where('meeting_id', $id)->delete();
        Meeting::query()->whereKey($id)->delete();
    }

    private function purgeMember(int $id): void
    {
        $member = Member::withTrashed()->find($id);
        if (! $member) {
            return;
        }

        $userId = $member->user_id;
        $member->bankAccounts()->delete();
        $member->nextOfKin()->delete();
        $member->beneficiaries()->delete();
        $member->profile()->delete();
        $member->forceDelete();

        if ($userId) {
            $this->purgeUser((int) $userId);
        }
    }

    private function purgeUser(int $id): void
    {
        $user = User::query()->find($id);
        if (! $user || $user->isCentralAdmin() || $user->isPlatformAdmin()) {
            return;
        }

        if ($user->member()->exists()) {
            return;
        }

        $user->delete();
    }

    private function purgeTransaction(?int $id): void
    {
        if (! $id) {
            return;
        }

        $txn = FinancialTransaction::query()->find($id);
        if (! $txn) {
            return;
        }

        $this->purgeJournal((int) $txn->journal_entry_id);
        JournalEntry::query()
            ->where('source_type', $txn->getMorphClass())
            ->where('source_id', $txn->id)
            ->get()
            ->each(fn (JournalEntry $entry) => $this->purgeJournal($entry->id));

        $txn->delete();
    }

    private function purgeJournal(?int $id): void
    {
        if (! $id) {
            return;
        }

        $entry = JournalEntry::query()->find($id);
        if (! $entry) {
            return;
        }

        if ($entry->reversed_by_entry_id) {
            $reversalId = (int) $entry->reversed_by_entry_id;
            $entry->update(['reversed_by_entry_id' => null]);
            $this->purgeJournal($reversalId);
        }

        JournalEntry::query()->where('reversed_by_entry_id', $entry->id)->update(['reversed_by_entry_id' => null]);
        FinancialTransaction::query()->where('journal_entry_id', $entry->id)->update(['journal_entry_id' => null]);
        $entry->lines()->delete();
        $entry->delete();
    }

    private function purgeApprovals(Model $model): void
    {
        $requests = ApprovalRequest::query()
            ->where('approvable_type', $model->getMorphClass())
            ->where('approvable_id', $model->getKey())
            ->get();

        foreach ($requests as $request) {
            ApprovalAction::query()->where('approval_request_id', $request->id)->delete();
            $request->delete();
        }
    }

    /**
     * @param  Collection<int, DemoSeedRecord>  $records
     * @return list<int>
     */
    private function ids($records, string $class): array
    {
        $morph = (new $class)->getMorphClass();

        return $records
            ->filter(fn (DemoSeedRecord $row) => in_array($row->model_type, [$class, $morph], true))
            ->pluck('model_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function payMonths(Member $member, ContributionProduct $product, int $amountKobo, int $months, int $offset): void
    {
        for ($m = $months; $m >= 1; $m--) {
            $period = now()->subMonths($m - 1)->startOfMonth();
            try {
                $contribution = $this->contributions->recordPayment(
                    $member,
                    $product,
                    $amountKobo,
                    'cash',
                    'demo:'.$product->id.':'.$member->id.':'.$period->format('Y-m').':'.$offset,
                    paidOn: $period->copy()->addDays(4)->toDateString(),
                    skipLimits: true,
                    forMonth: $period->format('Y-m'),
                );
                $this->track('savings', $contribution);
                $this->trackIf($contribution->financial_transaction_id, 'savings', FinancialTransaction::class);
            } catch (InvalidArgumentException) {
                //
            }
        }
    }

    /**
     * @return Collection<int, Member>
     */
    private function demoMembers()
    {
        $ids = DemoSeedRecord::query()
            ->where('feature', 'members')
            ->where('model_type', (new Member)->getMorphClass())
            ->pluck('model_id');

        $members = Member::query()->whereIn('id', $ids)->where('status', 'active')->get();

        if ($members->isNotEmpty()) {
            return $members;
        }

        return Member::query()->where('status', 'active')->limit(12)->get();
    }

    private function savingsProduct(): ?ContributionProduct
    {
        return ContributionProduct::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('bucket', ContributionBucket::Savings)
                    ->orWhere('name', 'like', '%saving%')
                    ->orWhere('name', 'like', '%thrift%');
            })
            ->first();
    }

    private function stsProduct(): ?ContributionProduct
    {
        return ContributionProduct::query()
            ->where('is_active', true)
            ->where('bucket', ContributionBucket::SpecialTarget)
            ->first();
    }

    private function loanProduct(): LoanProduct
    {
        $existing = LoanProduct::query()->where('is_active', true)->first();
        if ($existing) {
            return $existing;
        }

        $product = LoanProduct::query()->create([
            'name' => 'Demo personal loan',
            'minimum_amount_kobo' => 100_000,
            'maximum_amount_kobo' => 50_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'interest_method' => InterestMethod::Flat,
            'interest_rate_bps' => 1200,
            'processing_fee_kobo' => 0,
            'guarantors_required' => 0,
            'repayment_frequency' => ContributionFrequency::Monthly,
        ]);
        $this->track('loans', $product);

        return $product;
    }

    private function qardProduct(): IslamicFinancingProduct
    {
        $existing = IslamicFinancingProduct::query()
            ->where('structure', IslamicStructure::QardHasan)
            ->where('is_active', true)
            ->first();
        if ($existing) {
            return $existing;
        }

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'Demo Qard Hasan',
            'structure' => IslamicStructure::QardHasan,
            'minimum_amount_kobo' => 100_000,
            'maximum_amount_kobo' => 30_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'admin_fee_kobo' => 0,
            'shariah_status' => ShariahStatus::Approved,
            'is_active' => true,
        ]);
        $this->track('islamic', $product);

        return $product;
    }

    private function murabahaProduct(): IslamicFinancingProduct
    {
        $existing = IslamicFinancingProduct::query()
            ->where('structure', IslamicStructure::Murabaha)
            ->where('is_active', true)
            ->first();
        if ($existing) {
            return $existing;
        }

        $product = IslamicFinancingProduct::query()->create([
            'name' => 'Demo Murabaha',
            'structure' => IslamicStructure::Murabaha,
            'minimum_amount_kobo' => 100_000,
            'maximum_amount_kobo' => 50_000_000,
            'minimum_tenure_months' => 1,
            'maximum_tenure_months' => 12,
            'shariah_status' => ShariahStatus::Approved,
            'is_active' => true,
        ]);
        $this->track('islamic', $product);

        return $product;
    }

    private function contributionAmount(ContributionProduct $product, int $fallback): int
    {
        $amount = (int) ($product->default_amount_kobo ?: $fallback);
        $amount = max($amount, (int) $product->minimum_amount_kobo, 1);
        if ($product->maximum_amount_kobo) {
            $amount = min($amount, (int) $product->maximum_amount_kobo);
        }

        return max($amount, 1);
    }

    private function boundedAmount(int $min, ?int $max, int $preferred): int
    {
        $amount = max($min, $preferred);
        if ($max) {
            $amount = min($amount, $max);
        }

        return max($amount, $min ?: 100_000);
    }

    private function track(string $feature, Model $model): void
    {
        DemoSeedRecord::query()->create([
            'feature' => $feature,
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
        ]);
    }

    private function trackIf(?int $id, string $feature, string $class): void
    {
        if (! $id) {
            return;
        }

        $this->track($feature, $class::query()->findOrFail($id));
    }
}
