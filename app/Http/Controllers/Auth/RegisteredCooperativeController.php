<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Platform\TenantProvisioner;
use App\Enums\CooperativeMode;
use App\Enums\CooperativeType;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RegisteredCooperativeController extends Controller
{
    public function create()
    {
        abort_unless(\App\Models\PlatformSetting::get('allow_registration', true), 403, 'Self-serve registration is closed.');

        $plans = app(TenantProvisioner::class)->ensurePlans();

        return Inertia::render('Auth/Register', [
            'plans' => $plans->map(fn (SubscriptionPlan $p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'description' => $p->description,
                'price' => money((int) $p->monthly_price_kobo).'/mo',
                'members' => $p->member_limit
                    ? 'Up to '.number_format($p->member_limit).' members'
                    : 'Unlimited members',
            ]),
            'types' => collect(CooperativeType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
            'modes' => collect(CooperativeMode::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'hint' => match ($c) {
                    CooperativeMode::General => 'Savings and interest-bearing loans.',
                    CooperativeMode::Islamic => 'Qard Hasan and Murabaha. No interest engine.',
                    CooperativeMode::Hybrid => 'Conventional and Islamic products in one society.',
                },
            ]),
            'states' => config('coopos.nigeria_states'),
            'trial_days' => (int) \App\Models\PlatformSetting::get('trial_days', config('coopos.trial_days')),
            'default_plan' => \App\Models\PlatformSetting::get('default_plan', 'starter'),
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner)
    {
        $data = $request->validate([
            'admin_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', 'min:8'],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string'],
            'mode' => ['required', 'string'],
            'state' => ['nullable', 'string'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'plan' => ['nullable', 'string'],
        ]);

        $admin = User::query()->create([
            'name' => $data['admin_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'type' => UserType::Staff,
            'email_verified_at' => now(),
        ]);

        $tenant = $provisioner->onboard($data, $admin);

        if (! \App\Models\PlatformSetting::get('require_cooperative_approval', false)) {
            $provisioner->activate($tenant);
        }

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->to($admin->homeUrl())
            ->with('success', 'Welcome to CoopOS. Your cooperative workspace is ready.');
    }
}
