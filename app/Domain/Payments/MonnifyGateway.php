<?php

namespace App\Domain\Payments;

class MonnifyGateway implements PaymentGateway
{
    public function driver(): string
    {
        return 'monnify';
    }

    public function initialize(int $amountKobo, string $email, string $reference, array $payload = []): array
    {
        return [
            'reference' => $reference,
            'authorization_url' => null,
            'raw' => ['stub' => true],
        ];
    }

    public function verify(string $reference, array $payload = []): bool
    {
        return false;
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return false;
    }
}
