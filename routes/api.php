<?php

use App\Http\Controllers\Api\V1Controller;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\InitializeTenant;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/paystack', [WebhookController::class, 'paystack']);
Route::post('/webhooks/monipay', [WebhookController::class, 'monipay']);

Route::prefix('v1/{tenant}')
    ->middleware(['auth:sanctum', InitializeTenant::class])
    ->group(function () {
        Route::get('/members', [V1Controller::class, 'members']);
        Route::get('/contributions', [V1Controller::class, 'contributions']);
        Route::get('/loans', [V1Controller::class, 'loans']);
        Route::get('/transactions', [V1Controller::class, 'transactions']);
        Route::get('/reports', [V1Controller::class, 'reports']);
    });
