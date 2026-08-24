<?php

namespace App\Http\Controllers\Api;

use App\Domain\Payments\WebhookProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function paystack(Request $request, WebhookProcessor $processor)
    {
        $processor->handle(
            'paystack',
            $request->getContent(),
            $request->header('X-Paystack-Signature'),
            $request->all(),
        );

        return response()->json(['status' => 'ok']);
    }

    public function monipay(Request $request, WebhookProcessor $processor)
    {
        $processor->handle(
            'monipay',
            $request->getContent(),
            $request->header('X-Monipay-Signature'),
            $request->all(),
        );

        return response()->json(['status' => 'ok']);
    }
}
