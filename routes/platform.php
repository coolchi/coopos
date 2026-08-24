<?php

use App\Http\Controllers\Platform\AnnouncementController;
use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\Platform\BillingController;
use App\Http\Controllers\Platform\CooperativeController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SettingsController;
use App\Http\Controllers\Platform\TicketController;
use App\Http\Controllers\Platform\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'superadmin', 'central'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/cooperatives', [CooperativeController::class, 'index'])->name('cooperatives');
        Route::get('/cooperatives/{tenant}', [CooperativeController::class, 'show'])->name('cooperatives.show');
        Route::post('/cooperatives/{tenant}/approve', [CooperativeController::class, 'approve'])->name('cooperatives.approve');
        Route::post('/cooperatives/{tenant}/suspend', [CooperativeController::class, 'suspend'])->name('cooperatives.suspend');
        Route::post('/cooperatives/{tenant}/reactivate', [CooperativeController::class, 'reactivate'])->name('cooperatives.reactivate');
        Route::post('/cooperatives/{tenant}/impersonate', [ImpersonationController::class, 'store'])->name('cooperatives.impersonate');
        Route::post('/cooperatives/{tenant}/plan', [CooperativeController::class, 'changePlan'])->name('cooperatives.plan');

        Route::get('/invoices', [BillingController::class, 'invoices'])->name('invoices');
        Route::post('/invoices/{invoice}/paid', [BillingController::class, 'markPaid'])->name('invoices.paid');
        Route::post('/invoices/{invoice}/void', [BillingController::class, 'void'])->name('invoices.void');
        Route::get('/subscriptions', [BillingController::class, 'subscriptions'])->name('subscriptions');
        Route::post('/subscriptions/{subscription}/status', [BillingController::class, 'updateStatus'])->name('subscriptions.status');

        Route::get('/plans', [PlanController::class, 'index'])->name('plans');
        Route::post('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');

        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}', [TicketController::class, 'reply'])->name('tickets.reply');

        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit');

        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
