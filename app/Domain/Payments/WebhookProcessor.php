<?php

namespace App\Domain\Payments;

use App\Domain\Savings\ContributionService;
use App\Enums\TransactionStatus;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookProcessor
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly ContributionService $contributions,
        private readonly VirtualAccountService $virtualAccounts,
    ) {}

    public function handle(string $provider, string $rawPayload, ?string $signature, array $decoded): PaymentWebhook
    {
        $gateway = $this->gateways->driver($provider);

        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
        $idempotency = $data['reference'] ?? $decoded['id'] ?? hash('sha256', $rawPayload);

        $existing = PaymentWebhook::query()->where('idempotency_key', $provider.':'.$idempotency)->first();
        if ($existing) {
            return $existing;
        }

        $webhook = PaymentWebhook::query()->create([
            'provider' => $provider,
            'event' => $decoded['event'] ?? 'charge.success',
            'idempotency_key' => $provider.':'.$idempotency,
            'payload' => $decoded,
            'status' => 'received',
        ]);

        if (! $this->signatureValid($gateway, $provider, $rawPayload, $signature, $data)) {
            $webhook->update(['status' => 'rejected', 'error' => 'Invalid signature']);

            return $webhook;
        }

        try {
            DB::transaction(function () use ($webhook, $decoded, $provider, $data) {
                $reference = $data['reference'] ?? $decoded['reference'] ?? null;
                $payment = $reference
                    ? PaymentTransaction::query()->where('reference', $reference)->first()
                    : null;

                if ($payment && $payment->status === TransactionStatus::Successful) {
                    $webhook->update(['status' => 'duplicate', 'processed_at' => now()]);

                    return;
                }

                if ($payment) {
                    if ($payment->tenant_id) {
                        TenantContext::set($payment->tenant);
                    }

                    $payment->update([
                        'status' => TransactionStatus::Successful,
                        'provider_reference' => $data['id'] ?? null,
                        'paid_at' => now(),
                        'payload' => $decoded,
                    ]);

                    if ($payment->purpose === 'contribution' && $payment->member && $payment->payable) {
                        $this->contributions->recordPayment(
                            $payment->member,
                            $payment->payable,
                            $payment->amount_kobo,
                            $provider,
                            'pay:'.$payment->reference,
                        );
                    }
                } elseif ($provider === 'monipay' && ($decoded['event'] ?? '') === 'charge.success') {
                    $account = $this->virtualAccounts->findAccount($data);
                    if ($account?->tenant_id) {
                        TenantContext::set(Tenant::query()->find($account->tenant_id));
                    }
                    $this->virtualAccounts->creditFromCharge($data);
                }

                $webhook->update(['status' => 'processed', 'processed_at' => now()]);
            });
        } catch (\Throwable $e) {
            $webhook->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }

        return $webhook->fresh();
    }

    public function createPending(string $provider, int $amountKobo, string $purpose, $payable = null, $member = null): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'tenant_id' => tenant()?->id,
            'branch_id' => $member?->branch_id,
            'member_id' => $member?->id,
            'provider' => $provider,
            'reference' => 'PAY-'.strtoupper(Str::ulid()),
            'amount_kobo' => $amountKobo,
            'status' => TransactionStatus::Pending,
            'purpose' => $purpose,
            'payable_type' => $payable?->getMorphClass(),
            'payable_id' => $payable?->getKey(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function signatureValid(PaymentGateway $gateway, string $provider, string $rawPayload, ?string $signature, array $data): bool
    {
        if ($gateway->verifyWebhookSignature($rawPayload, $signature)) {
            return true;
        }

        if ($provider !== 'monipay') {
            return false;
        }

        $account = $this->virtualAccounts->findAccount($data);
        if (! $account?->tenant_id) {
            return false;
        }

        $tenant = Tenant::query()->find($account->tenant_id);

        return $tenant
            ? (new MonipayClient($tenant))->verifyWebhookSignature($rawPayload, $signature)
            : false;
    }
}
