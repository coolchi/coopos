<?php

namespace App\Http\Controllers\Platform;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    public function store(Tenant $tenant)
    {
        abort_unless(PlatformSetting::get('allow_impersonation', true), 403, 'Impersonation is disabled.');

        $admin = $tenant->users()
            ->where('is_platform_admin', false)
            ->where('type', UserType::Staff)
            ->first()
            ?? $tenant->users()->where('is_platform_admin', false)->first();
        abort_unless($admin, 404, 'This cooperative has no administrator yet.');

        session([
            'impersonator_id' => auth()->id(),
            'impersonate_tenant_id' => $tenant->id,
        ]);

        TenantContext::set($tenant);
        auth()->login($admin);
        request()->session()->regenerate();

        activity()->causedBy($admin)->performedOn($tenant)->log('impersonated tenant');

        return Inertia::location($tenant->appUrl('/dashboard'));
    }

    public function leave(Request $request)
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        $request->session()->forget('impersonate_tenant_id');

        abort_unless($impersonatorId, 403);

        $admin = User::query()->findOrFail($impersonatorId);
        TenantContext::forget();
        TenantContext::bypass();
        auth()->login($admin);
        $request->session()->regenerate();
        $request->session()->flash('success', 'You are back on the platform.');

        return Inertia::location(Tenancy::centralUrl('/platform'));
    }
}
