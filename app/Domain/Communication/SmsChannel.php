<?php

namespace App\Domain\Communication;

use Illuminate\Support\Facades\Log;

class SmsChannel
{
    public function send(string $phone, string $message): void
    {
        Log::info('sms.stub', compact('phone', 'message'));
    }
}
