<?php

namespace App\Domain\Payments;

use App\Domain\Ledger\WalletService;
use App\Models\Member;
use App\Models\MemberVirtualAccount;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use RuntimeException;

class VirtualAccountService
{
    public function issue(Member $member, ?string $bvn = null, ?string $nin = null): MemberVirtualAccount
    {
        $member->loadMissing('profile');
        $client = new MonipayClient($member->tenant ?? tenant());

        $email = $member->email ?: $this->fallbackEmail($member);
        $bvn = $bvn ?: $member->profile?->bvn_encrypted;
        $nin = $nin ?: $member->profile?->nin_encrypted;

        $wallet = $client->createCustomerWallet(array_filter([
            'email' => $email,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'name' => $member->fullName(),
            'phone' => $member->phone,
            'bvn' => $bvn,
            'nin' => $nin,
            'reference' => 'MEM-'.$member->membership_number,
        ], fn ($value) => $value !== null && $value !== ''));

        $account = $this->accountFromWallet($wallet);

        return MemberVirtualAccount::query()->updateOrCreate(
            [
                'member_id' => $member->id,
                'provider' => 'monipay',
            ],
            [
                'wallet_id' => $wallet['wallet_id'] ?? null,
                'customer_id' => $wallet['customer_id'] ?? null,
                'reference' => $wallet['reference'] ?? null,
                'account_name' => $account['account_name'] ?? $wallet['account_name'] ?? $member->fullName(),
                'account_number' => $account['account_number'] ?? $wallet['account_number'] ?? null,
                'bank_name' => $account['bank_name'] ?? $wallet['bank_name'] ?? 'Monipay',
                'bank_code' => $account['bank_code'] ?? $wallet['bank_code'] ?? null,
                'status' => strtolower((string) ($wallet['status'] ?? 'active')),
                'meta' => [
                    'customer_email' => $wallet['customer_email'] ?? $email,
                    'frozen' => (bool) ($wallet['frozen'] ?? false),
                ],
                'issued_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    public function creditFromCharge(array $charge): void
    {
        $account = $this->findAccount($charge);

        if (! $account) {
            return;
        }

        if ($account->tenant_id && ! TenantContext::id()) {
            TenantContext::set(Tenant::query()->find($account->tenant_id));
        }

        $member = Member::query()->withoutGlobalScope(TenantScope::class)->find($account->member_id);

        if (! $member) {
            throw new RuntimeException('Member missing for Monipay credit.');
        }

        $amountKobo = $this->amountKobo($charge);
        if ($amountKobo <= 0) {
            return;
        }

        $reference = (string) ($charge['reference'] ?? $charge['id'] ?? Str::ulid());

        app(WalletService::class)->credit($member, $amountKobo, 'monipay', 'pay:'.$reference);
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    public function findAccount(array $charge): ?MemberVirtualAccount
    {
        $query = MemberVirtualAccount::query()->withoutGlobalScopes();
        $number = $charge['account_number']
            ?? $charge['virtual_account']['account_number'] ?? null;
        $walletId = $charge['wallet_id'] ?? $charge['customer_wallet_id'] ?? null;
        $customerId = $charge['customer_id'] ?? $charge['customer']['id'] ?? $charge['customer']['customer_code'] ?? null;

        if ($walletId) {
            $match = (clone $query)->where('wallet_id', $walletId)->first();
            if ($match) {
                return $match;
            }
        }
        if ($number) {
            $match = (clone $query)->where('account_number', $number)->first();
            if ($match) {
                return $match;
            }
        }
        if ($customerId) {
            return (clone $query)->where('customer_id', $customerId)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $wallet
     * @return array<string, mixed>
     */
    private function accountFromWallet(array $wallet): array
    {
        $accounts = $wallet['virtual_accounts'] ?? [];
        $first = is_array($accounts) && $accounts !== [] ? $accounts[0] : [];

        return is_array($first) ? $first : [];
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    private function amountKobo(array $charge): int
    {
        $amount = $charge['amount'] ?? $charge['amount_kobo'] ?? 0;

        if (is_float($amount) || (is_string($amount) && str_contains($amount, '.'))) {
            return naira_to_kobo($amount);
        }

        return (int) $amount;
    }

    private function fallbackEmail(Member $member): string
    {
        $slug = tenant()?->slug ?: 'coop';

        return Str::lower($member->membership_number).'@members.'.$slug.'.coopos.test';
    }
}
