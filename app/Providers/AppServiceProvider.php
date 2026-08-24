<?php

namespace App\Providers;

use App\Domain\Payments\PaymentGatewayManager;
use App\Jobs\ProcessContributionReminders;
use App\Models\PlatformSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(8)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('platform_settings')) {
                    PlatformSetting::applyToConfig();
                }
            } catch (\Throwable) {
                // Database may not be ready during install.
            }
        }
    }
}
