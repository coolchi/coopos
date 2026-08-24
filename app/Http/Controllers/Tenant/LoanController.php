<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Lending\LoanEligibilityService;
use App\Domain\Lending\LoanService;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Support\FiscalYearContext;
use App\Support\LoanDomainError;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class LoanController extends Controller
{
    public function products()
    {
        return redirect()->route('tenant.products.index', ['tenant' => tenant()?->slug]);
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'minimum_amount' => ['required', 'numeric'],
            'maximum_amount' => ['required', 'numeric'],
            'minimum_tenure_months' => ['required', 'integer', 'min:1'],
            'maximum_tenure_months' => ['required', 'integer', 'min:1'],
            'interest_method' => ['required', 'string'],
            'interest_rate' => ['required', 'numeric', 'min:0'],
            'processing_fee' => ['nullable', 'numeric'],
            'guarantors_required' => ['nullable', 'integer', 'min:0'],
        ]);

        LoanProduct::query()->create([
            'name' => $data['name'],
            'minimum_amount_kobo' => naira_to_kobo($data['minimum_amount']),
            'maximum_amount_kobo' => naira_to_kobo($data['maximum_amount']),
            'minimum_tenure_months' => $data['minimum_tenure_months'],
            'maximum_tenure_months' => $data['maximum_tenure_months'],
            'interest_method' => $data['interest_method'],
            'interest_rate_bps' => (int) round($data['interest_rate'] * 100),
            'processing_fee_kobo' => naira_to_kobo($data['processing_fee'] ?? 0),
            'guarantors_required' => $data['guarantors_required'] ?? 0,
            'repayment_frequency' => 'monthly',
        ]);

        return back()->with('success', 'Loan product created.');
    }

    public function applications()
    {
        $applications = LoanApplication::query()->with('member', 'product', 'guarantors');
        FiscalYearContext::constrainDateOrCreated($applications, 'submitted_at');

        return Inertia::render('Tenant/Loans/Applications', [
            'applications' => $applications->latest()->paginate(20)
                ->through(fn ($a) => [
                    'uuid' => $a->uuid,
                    'member' => $a->member->fullName(),
                    'product' => $a->product->name,
                    'amount' => money($a->amount_kobo),
                    'tenure' => $a->tenure_months,
                    'status' => $a->status->value,
                ]),
            'members' => Member::query()->where('status', 'active')->orderBy('first_name')->get(),
            'products' => LoanProduct::query()->where('is_active', true)->get(),
        ]);
    }

    public function apply(Request $request, LoanService $loans)
    {
        $data = $request->validate([
            'member_uuid' => ['required'],
            'product_uuid' => ['required'],
            'amount' => ['required', 'numeric', 'min:1'],
            'tenure_months' => ['required', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string'],
            'guarantors' => ['array'],
        ]);

        try {
            $loans->apply(
                Member::query()->where('uuid', $data['member_uuid'])->firstOrFail(),
                LoanProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail(),
                naira_to_kobo($data['amount']),
                $data['tenure_months'],
                $data['purpose'] ?? '',
                Member::query()->whereIn('uuid', $data['guarantors'] ?? [])->pluck('id')->all(),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                LoanDomainError::field($e->getMessage()) => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Loan application submitted.');
    }

    public function eligibility(Request $request, LoanEligibilityService $eligibility)
    {
        $data = $request->validate([
            'member_uuid' => ['required', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $member = Member::query()->where('uuid', $data['member_uuid'])->firstOrFail();

        return response()->json($eligibility->evaluate($member, naira_to_kobo($data['amount'] ?? 0)));
    }

    public function preview(Request $request, LoanService $loans)
    {
        $data = $request->validate([
            'product_uuid' => ['required'],
            'amount' => ['required', 'numeric'],
            'tenure_months' => ['required', 'integer'],
        ]);

        $preview = $loans->preview(
            LoanProduct::query()->where('uuid', $data['product_uuid'])->firstOrFail(),
            naira_to_kobo($data['amount']),
            $data['tenure_months'],
        );

        return response()->json([
            'interest' => money($preview['interest_kobo']),
            'total' => money($preview['total_repayable_kobo']),
            'schedule' => collect($preview['schedule'])->map(fn ($r) => [
                ...$r,
                'amount' => money($r['amount_due_kobo']),
            ]),
        ]);
    }

    public function index()
    {
        $loans = Loan::query()->with('member', 'product');
        FiscalYearContext::constrainDateOrCreated($loans, 'disbursed_on');

        return Inertia::render('Tenant/Loans/Index', [
            'loans' => $loans->latest()->paginate(20)
                ->through(fn ($l) => [
                    'uuid' => $l->uuid,
                    'account_number' => $l->account_number,
                    'member' => $l->member->fullName(),
                    'product' => $l->product->name,
                    'principal' => money($l->principal_kobo),
                    'outstanding' => money($l->outstanding_kobo),
                    'status' => $l->status->value,
                ]),
        ]);
    }

    public function show(string $tenant, Loan $loan)
    {
        $loan->load('member', 'product', 'repayments');
        $next = $loan->repayments->first(fn ($row) => $row->status !== 'paid');

        return Inertia::render('Tenant/Loans/Show', [
            'loan' => [
                'uuid' => $loan->uuid,
                'account_number' => $loan->account_number,
                'member' => $loan->member->fullName(),
                'product' => $loan->product?->name,
                'principal' => money($loan->principal_kobo),
                'outstanding' => money($loan->outstanding_kobo),
                'outstanding_naira' => $loan->outstanding_kobo / 100,
                'status' => $loan->status->value,
                'next_due' => $next?->due_on?->toDateString(),
                'next_amount' => $next ? money($next->outstandingKobo()) : null,
                'next_amount_naira' => $next ? $next->outstandingKobo() / 100 : '',
            ],
            'repayments' => $loan->repayments->map(fn ($r) => [
                ...$r->toArray(),
                'amount_due' => money($r->amount_due_kobo),
                'amount_paid' => money($r->amount_paid_kobo),
            ]),
        ]);
    }

    public function disburse(string $tenant, LoanApplication $application, LoanService $loans)
    {
        $loans->disburse($application);

        return back()->with('success', 'Loan disbursed.');
    }

    public function repay(Request $request, string $tenant, Loan $loan, LoanService $loans)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'channel' => ['nullable', 'string', 'in:cash,transfer,bank_transfer,manual'],
            'paid_on' => ['nullable', 'date'],
        ]);
        $postedAt = ! empty($data['paid_on']) ? Carbon::parse($data['paid_on'])->endOfDay() : now();
        $loans->repay($loan, naira_to_kobo($data['amount']), $data['channel'] ?: 'cash', postedAt: $postedAt);

        return back()->with('success', 'Repayment recorded.');
    }

    public function guarantee(Request $request, string $tenant, LoanGuarantor $guarantor, LoanService $loans)
    {
        $loans->respondToGuarantee($guarantor, $request->boolean('accept'), $request->input('note'));

        return back()->with('success', 'Guarantee response saved.');
    }
}
