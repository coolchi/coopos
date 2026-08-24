<?php

namespace App\Domain\Payments;

interface PaymentGateway
{
    public function driver(): string;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{authorization_url?: string, reference: string, raw: mixed}
     */
    public function initialize(int $amountKobo, string $email, string $reference, array $payload = []): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(string $reference, array $payload = []): bool;

    public function verifyWebhookSignature(string $payload, ?string $signature): bool;
}
