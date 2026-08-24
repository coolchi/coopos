<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Ledger\LedgerService;
use App\Domain\Membership\MemberService;
use App\Domain\Payments\MonipayClient;
use App\Domain\Payments\VirtualAccountService;
use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberGuarantor;
use App\Models\MemberVirtualAccount;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $members = Member::query()
            ->with(['profile', 'branch', 'sponsor', 'virtualAccounts'])
            ->withSum('contributions', 'amount_kobo')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->branch, fn ($q, $uuid) => $q->whereHas('branch', fn ($b) => $b->where('uuid', $uuid)))
            ->when($request->q, function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('membership_number', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tenant/Members/Index', [
            'filters' => $request->only('q', 'status', 'branch'),
            'branches' => $this->branchOptions(),
            'states' => config('coopos.nigeria_states'),
            'locked_branch' => BranchContext::isConstrained(),
            'member_options' => Member::query()->where('status', MemberStatus::Active)->orderBy('first_name')
                ->get(['uuid', 'first_name', 'last_name', 'membership_number']),
            'introducers' => Member::query()->orderBy('first_name')
                ->get(['uuid', 'first_name', 'last_name', 'membership_number']),
            'products' => ContributionController::paymentProductOptions(),
            ...ContributionController::paymentCatalog(),
            'monipay_configured' => (new MonipayClient)->configured(),
            'counts' => [
                'all' => Member::query()->count(),
                'active' => Member::query()->where('status', MemberStatus::Active)->count(),
                'pending' => Member::query()->where('status', MemberStatus::Pending)->count(),
            ],
            'members' => $members->through(fn (Member $m) => [
                'uuid' => $m->uuid,
                'membership_number' => $m->membership_number,
                'name' => $m->fullName(),
                'phone' => $m->phone,
                'email' => $m->email,
                'status' => $m->status->value,
                'branch' => $m->branch?->name,
                'introducer' => $m->sponsor?->fullName(),
                'joined_on' => $m->joined_on?->toDateString(),
                'contribution' => money((int) ($m->contributions_sum_amount_kobo ?? 0)),
                'virtual_account' => $this->virtualAccountPayload($m->virtualAccounts->first()),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Tenant/Members/Create', [
            'states' => config('coopos.nigeria_states'),
            'branches' => $this->branchOptions(),
            'locked_branch' => BranchContext::isConstrained(),
            'introducers' => Member::query()->orderBy('first_name')->get(['uuid', 'first_name', 'last_name', 'membership_number']),
            'default_branch_uuid' => $request->query('branch'),
        ]);
    }

    public function store(Request $request, MemberService $service)
    {
        $member = $service->register($this->validated($request));

        return redirect()->route('tenant.members.show', [tenant()->slug, $member])
            ->with('success', 'Member registered.');
    }

    public function show(string $tenant, Member $member, LedgerService $ledger)
    {
        $member->load([
            'profile',
            'nextOfKin',
            'bankAccounts',
            'virtualAccounts',
            'branch',
            'sponsor',
            'memberGuarantors.guarantor',
            'enrollments.product',
            'loans',
            'qardHasan',
            'murabaha',
        ]);
        $kin = $member->nextOfKin->first();

        $savingsKobo = 0;
        try {
            $savingsKobo = $ledger->memberSavingsBalance($member);
        } catch (RuntimeException) {
            $savingsKobo = 0;
        }

        $outstandingKobo = (int) $member->loans->sum('outstanding_kobo')
            + (int) $member->qardHasan->sum('outstanding_kobo')
            + (int) $member->murabaha->sum('outstanding_kobo');

        $contributionsTotal = (int) $member->contributions()->sum('amount_kobo');
        $contributions = $member->contributions()
            ->with('product')
            ->latest('paid_on')
            ->latest('id')
            ->paginate(8, ['*'], 'contributions_page')
            ->withQueryString()
            ->through(fn ($c) => [
                'uuid' => $c->uuid,
                'product' => $c->product?->name,
                'amount' => money($c->amount_kobo),
                'paid_on' => $c->paid_on?->toDateString(),
                'channel' => $c->channel,
            ]);

        return Inertia::render('Tenant/Members/Show', [
            'states' => config('coopos.nigeria_states'),
            'branches' => $this->branchOptions(),
            'locked_branch' => BranchContext::isConstrained(),
            'products' => ContributionController::paymentProductOptions(),
            ...ContributionController::paymentCatalog($member->uuid),
            'introducers' => Member::query()->where('id', '!=', $member->id)->orderBy('first_name')
                ->get(['uuid', 'first_name', 'last_name', 'membership_number']),
            'monipay_configured' => (new MonipayClient)->configured(),
            'kpis' => [
                'savings' => money($savingsKobo),
                'contributions_total' => money($contributionsTotal),
                'outstanding' => money($outstandingKobo),
                'plans' => $member->enrollments->where('status', 'active')->count(),
            ],
            'member' => [
                'uuid' => $member->uuid,
                'membership_number' => $member->membership_number,
                'name' => $member->fullName(),
                'first_name' => $member->first_name,
                'middle_name' => $member->middle_name,
                'last_name' => $member->last_name,
                'gender' => $member->gender,
                'date_of_birth' => $member->date_of_birth?->toDateString(),
                'phone' => $member->phone,
                'email' => $member->email,
                'status' => $member->status->value,
                'branch_uuid' => $member->branch?->uuid,
                'branch' => $member->branch?->name,
                'introducer' => $member->sponsor?->fullName(),
                'introducer_uuid' => $member->sponsor?->uuid,
                'guarantors' => $member->memberGuarantors
                    ->filter(fn ($row) => $row->guarantor)
                    ->map(fn ($row) => [
                        'uuid' => $row->uuid,
                        'member_uuid' => $row->guarantor->uuid,
                        'name' => $row->guarantor->fullName(),
                        'membership_number' => $row->guarantor->membership_number,
                        'status' => $row->guarantor->status->value,
                    ])
                    ->values(),
                'occupation' => $member->occupation,
                'employer' => $member->employer,
                'joined_on' => $member->joined_on?->toDateString(),
                'address' => $member->profile?->residential_address,
                'state' => $member->profile?->state,
                'lga' => $member->profile?->lga,
                'nin' => $member->profile?->nin_encrypted,
                'next_of_kin_name' => $kin?->name,
                'next_of_kin_relationship' => $kin?->relationship,
                'next_of_kin_phone' => $kin?->phone,
                'next_of_kin_address' => $kin?->address,
                'bank_accounts' => $member->bankAccounts->map(fn ($a) => [
                    'bank' => $a->bank_name ?? $a->bank,
                    'number' => $a->account_number,
                    'name' => $a->account_name,
                    'is_primary' => $a->is_primary,
                    'is_verified' => $a->is_verified,
                ]),
                'virtual_account' => $this->virtualAccountPayload($member->virtualAccounts->first()),
                'enrollments' => $member->enrollments->map(fn ($e) => [
                    'uuid' => $e->uuid,
                    'product' => $e->product?->name,
                    'amount' => money($e->amount_kobo),
                    'status' => $e->status,
                    'starts_on' => $e->starts_on?->toDateString(),
                ]),
                'contributions' => $contributions,
                'loans' => $member->loans->map(fn ($l) => [
                    'uuid' => $l->uuid,
                    'account' => $l->account_number,
                    'principal' => money($l->principal_kobo),
                    'outstanding' => money($l->outstanding_kobo),
                    'status' => $l->status->value,
                ]),
                'qard' => $member->qardHasan->map(fn ($f) => [
                    'uuid' => $f->uuid,
                    'principal' => money($f->principal_kobo),
                    'outstanding' => money($f->outstanding_kobo),
                    'status' => $f->status->value,
                ]),
                'murabaha' => $member->murabaha->map(fn ($f) => [
                    'uuid' => $f->uuid,
                    'asset' => $f->asset_description,
                    'sale_price' => money($f->sale_price_kobo),
                    'outstanding' => money($f->outstanding_kobo),
                    'status' => $f->status->value,
                ]),
            ],
        ]);
    }

    public function update(Request $request, string $tenant, Member $member, MemberService $service)
    {
        $service->update($member, $this->validated($request, false));

        return back()->with('success', 'Member updated.');
    }

    public function approve(string $tenant, Member $member, MemberService $service)
    {
        $service->approve($member);

        return back()->with('success', 'Member activated.');
    }

    public function issueVirtualAccount(string $tenant, Member $member, VirtualAccountService $accounts)
    {
        try {
            $accounts->issue($member);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Monipay virtual account issued.');
    }

    public function storeGuarantor(Request $request, string $tenant, Member $member, MemberService $service)
    {
        $data = $request->validate([
            'guarantor_uuid' => ['required', 'uuid'],
        ]);

        $guarantor = Member::query()->where('uuid', $data['guarantor_uuid'])->first();

        if (! $guarantor) {
            return back()->withErrors(['guarantor_uuid' => 'Choose an existing member as guarantor.']);
        }

        $service->addGuarantor($member, $guarantor);

        return back()->with('success', 'Guarantor added.');
    }

    public function removeGuarantor(string $tenant, Member $member, MemberGuarantor $guarantor, MemberService $service)
    {
        $service->removeGuarantor($member, $guarantor);

        return back()->with('success', 'Guarantor removed.');
    }

    public function importTemplate()
    {
        $csv = implode("\n", [
            'first_name,last_name,email,phone,state,occupation,branch,introducer',
            'Ada,Okeke,ada@example.com,08012345678,Lagos,Trader,HQ,',
            'Chinedu,Okafor,,08098765432,Kano,Farmer,HQ,M10001',
        ])."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="members-import-template.csv"',
        ]);
    }

    public function import(Request $request, MemberService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);

        if ($header === [] || $header === [null] || $header === ['']) {
            fclose($handle);

            return back()->with('error', 'The CSV file is missing a header row.');
        }

        if (! in_array('first_name', $header, true) || ! in_array('last_name', $header, true)) {
            fclose($handle);

            return back()->with('error', 'CSV must include first_name and last_name columns.');
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if ($this->csvRowIsEmpty($data)) {
                continue;
            }
            $padded = array_pad($data, count($header), '');
            $rows[] = array_combine($header, array_slice($padded, 0, count($header)));
        }
        fclose($handle);

        if ($rows === []) {
            return back()->with('error', 'No member rows found in the CSV.');
        }

        $result = $service->importCsv($rows);
        $errors = array_slice($result['errors'], 0, 50);

        $message = $result['failed'] === 0
            ? "Imported {$result['imported']} members."
            : "Imported {$result['imported']} members. Failed {$result['failed']}.";

        return back()
            ->with($result['failed'] ? 'error' : 'success', $message)
            ->with('import_errors', $errors);
    }

    /**
     * @param  array<int, string|null>|false  $data
     */
    private function csvRowIsEmpty(array|false $data): bool
    {
        if ($data === false) {
            return true;
        }

        foreach ($data as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating = true): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'gender' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'password' => [$creating ? 'nullable' : 'prohibited', 'min:8'],
            'address' => ['nullable', 'string'],
            'state' => ['nullable', 'string'],
            'lga' => ['nullable', 'string'],
            'occupation' => ['nullable', 'string'],
            'employer' => ['nullable', 'string'],
            'bvn' => ['nullable', 'string', 'max:11'],
            'nin' => ['nullable', 'string', 'max:11'],
            'next_of_kin_name' => ['nullable', 'string'],
            'next_of_kin_relationship' => ['nullable', 'string'],
            'next_of_kin_phone' => ['nullable', 'string'],
            'next_of_kin_address' => ['nullable', 'string', 'max:255'],
            'branch_uuid' => [BranchContext::isConstrained() || ! $creating ? 'nullable' : 'required', 'uuid'],
            'introducer_uuid' => ['nullable', 'uuid'],
            'guarantor_uuid' => ['nullable', 'uuid'],
        ]);

        if (BranchContext::isConstrained()) {
            $data['branch_id'] = BranchContext::id();
        } elseif (! empty($data['branch_uuid'])) {
            $data['branch_id'] = Branch::query()->where('uuid', $data['branch_uuid'])->firstOrFail()->id;
        }

        if (array_key_exists('introducer_uuid', $data)) {
            $data['sponsor_member_id'] = $data['introducer_uuid']
                ? Member::query()->where('uuid', $data['introducer_uuid'])->value('id')
                : null;
        }

        if (array_key_exists('guarantor_uuid', $data)) {
            $data['guarantor_member_id'] = $data['guarantor_uuid']
                ? Member::query()->where('uuid', $data['guarantor_uuid'])->value('id')
                : null;
        }

        return $data;
    }

    /**
     * @return array{account_name: string|null, account_number: string|null, bank_name: string|null, status: string|null}|null
     */
    private function virtualAccountPayload(?MemberVirtualAccount $account): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            'account_name' => $account->account_name,
            'account_number' => $account->account_number,
            'bank_name' => $account->bank_name,
            'status' => $account->status,
        ];
    }

    /**
     * @return list<array{uuid: string, name: string, code: string}>
     */
    private function branchOptions(): array
    {
        if (! Schema::hasTable('branches')) {
            return [];
        }

        return Branch::query()->where('is_active', true)->orderByDesc('is_headquarters')->orderBy('name')
            ->get(['uuid', 'name', 'code'])
            ->map(fn (Branch $b) => [
                'uuid' => $b->uuid,
                'name' => $b->name,
                'code' => $b->code,
            ])
            ->all();
    }
}
