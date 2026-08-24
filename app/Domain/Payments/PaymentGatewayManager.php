<?php

namespace App\Domain\Payments;

use InvalidArgumentException;

class PaymentGatewayManager
{
    public function driver(?string $name = null): PaymentGateway
    {
        return match ($name ?? config('services.payments.default', 'manual')) {
            'paystack' => app(PaystackGateway::class),
            'flutterwave' => app(FlutterwaveGateway::class),
            'monnify' => app(MonnifyGateway::class),
            'monipay' => app(MonipayGateway::class),
            'manual' => app(ManualGateway::class),
            default => throw new InvalidArgumentException("Unknown payment driver [{$name}]."),
        };
    }
}
