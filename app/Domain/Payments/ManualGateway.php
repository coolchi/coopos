<?php

namespace App\Domain\Payments;

class ManualGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'manual';
    }

    public function initialize(int $amountKobo, string $email, string $reference, array $payload = []): array
    {
        return [
            'reference' => $reference,
            'authorization_url' => null,
            'raw' => ['channel' => 'manual'],
        ];
    }

    public function verify(string $reference, array $payload = []): bool
    {
        return true;
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return true;
    }
}
