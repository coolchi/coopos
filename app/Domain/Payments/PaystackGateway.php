<?php

namespace App\Domain\Payments;

use Illuminate\Support\Facades\Http;

class PaystackGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'paystack';
    }

    public function initialize(int $amountKobo, string $email, string $reference, array $payload = []): array
    {
        $response = Http::withToken(config('services.paystack.secret'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => $amountKobo,
                'reference' => $reference,
                'currency' => 'NGN',
                'metadata' => $payload,
                'callback_url' => $payload['callback_url'] ?? null,
            ])
            ->throw()
            ->json();

        return [
            'authorization_url' => $response['data']['authorization_url'] ?? null,
            'reference' => $response['data']['reference'] ?? $reference,
            'raw' => $response,
        ];
    }

    public function verify(string $reference, array $payload = []): bool
    {
        $response = Http::withToken(config('services.paystack.secret'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}")
            ->throw()
            ->json();

        return ($response['data']['status'] ?? null) === 'success';
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.paystack.secret');

        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }
}
