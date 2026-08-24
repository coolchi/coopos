<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Ledger\AccountingService;
use App\Domain\Ledger\LedgerService;
use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use InvalidArgumentException;

class AccountController extends Controller
{
    public function index(AccountingService $accounting, LedgerService $ledger)
    {
        $accounting->ensureChart();

        $accounts = Account::query()
            ->withCount('lines')
            ->orderByRaw("case type when 'asset' then 1 when 'liability' then 2 when 'equity' then 3 when 'income' then 4 else 5 end")
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($ledger) {
                try {
                    $balance = $ledger->accountBalance($account->code);
                } catch (\RuntimeException) {
                    $balance = 0;
                }

                return [
                    'uuid' => $account->uuid,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type?->value ?? (string) $account->type,
                    'type_label' => $account->typeLabel(),
                    'description' => $account->description,
                    'sort_order' => $account->sort_order,
                    'is_system' => $account->is_system,
                    'is_active' => $account->is_active,
                    'has_activity' => $account->lines_count > 0,
                    'balance' => money($balance),
                    'balance_kobo' => $balance,
                ];
            });

        return Inertia::render('Tenant/Accounting/Chart', [
            'accounts' => $accounts,
            'types' => collect(AccountType::cases())->map(fn (AccountType $type) => [
                'value' => $type->value,
                'label' => ucfirst($type->value),
            ]),
            'counts' => [
                'all' => $accounts->count(),
                'asset' => $accounts->where('type', 'asset')->count(),
                'liability' => $accounts->where('type', 'liability')->count(),
                'equity' => $accounts->where('type', 'equity')->count(),
                'income' => $accounts->where('type', 'income')->count(),
                'expense' => $accounts->where('type', 'expense')->count(),
            ],
        ]);
    }

    public function store(Request $request, AccountingService $accounting)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('accounts', 'code')->where('tenant_id', tenant_or_fail()->id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        try {
            $accounting->createAccount($data);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Account added to the chart.');
    }

    public function update(Request $request, string $tenant, Account $account, AccountingService $accounting)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(AccountType::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $accounting->updateAccount($account, $data);

        return back()->with('success', 'Account updated.');
    }

    public function toggle(string $tenant, Account $account, AccountingService $accounting)
    {
        $accounting->toggleAccount($account);

        return back()->with('success', $account->fresh()->is_active ? 'Account activated.' : 'Account deactivated.');
    }

    public function destroy(string $tenant, Account $account, AccountingService $accounting)
    {
        try {
            $accounting->deleteAccount($account);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Account removed.');
    }
}
