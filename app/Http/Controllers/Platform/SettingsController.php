<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Platform/Settings', [
            'settings' => PlatformSetting::current(),
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['name', 'slug']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'platform_name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'support_email' => ['required', 'email'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'from_email' => ['required', 'email'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'allow_registration' => ['required', 'boolean'],
            'require_cooperative_approval' => ['required', 'boolean'],
            'default_plan' => ['required', 'string'],
            'central_domain' => ['required', 'string', 'max:80'],
            'subdomains_enabled' => ['required', 'boolean'],
            'maintenance_mode' => ['required', 'boolean'],
            'allow_impersonation' => ['required', 'boolean'],
            'paystack_public_key' => ['nullable', 'string', 'max:120'],
            'sms_enabled' => ['required', 'boolean'],
            'islamic_finance_enabled' => ['required', 'boolean'],
            'hybrid_mode_enabled' => ['required', 'boolean'],
            'api_enabled' => ['required', 'boolean'],
        ]);

        PlatformSetting::putMany($data);

        return back()->with('success', 'Platform settings saved.');
    }
}
