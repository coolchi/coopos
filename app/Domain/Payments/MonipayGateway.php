<?php

namespace App\Domain\Payments;

class MonipayGateway implements PaymentGateway
{
    public function __construct(private readonly MonipayClient $client) {}

    public function driver(): string
    {
        return 'monipay';
    }

    public function initialize(int $amountKobo, string $email, string $reference, array $payload = []): array
    {
        return [
            'reference' => $reference,
            'authorization_url' => null,
            'raw' => ['stub' => false, 'amount' => $amountKobo, 'email' => $email, ...$payload],
        ];
    }

    public function verify(string $reference, array $payload = []): bool
    {
        return false;
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return $this->client->verifyWebhookSignature($payload, $signature);
    }
}
