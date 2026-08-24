<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'cooperative' => tenant() ? [
                'name' => tenant()->name,
                'slug' => tenant()->slug,
                'host' => tenant()->host(),
                'mode' => tenant()->mode->label(),
                'type' => tenant()->type->label(),
                'logo_path' => tenant()->logo_path,
                'logo_url' => tenant()->logoUrl(),
                'islamic' => tenant()->offersIslamic(),
            ] : null,
            'demo_logins' => $this->demoLogins(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again shortly.',
            ]);
        }

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return Inertia::location($user->homeUrl());
    }

    public function home(Request $request)
    {
        return Inertia::location($request->user()->homeUrl());
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * @return list<array{label: string, hint: string, email: string, password: string}>
     */
    private function demoLogins(): array
    {
        if (app()->isProduction()) {
            return [];
        }

        $password = 'Password1';
        $tenant = tenant();

        if ($tenant) {
            $staff = match ($tenant->slug) {
                'ijebu' => [
                    ['label' => 'Staff', 'hint' => 'Adewale Treasurer', 'email' => 'ursula.b@example.com'],
                ],
                'barakah' => [
                    ['label' => 'Tenant Admin', 'hint' => 'Aisha Manager', 'email' => 'xena.w@example.org'],
                    ['label' => 'Branch Admin', 'hint' => 'Zainab Operations', 'email' => 'hq.barakah@coopos.test'],
                ],
                'traders' => [
                    ['label' => 'Staff', 'hint' => 'Chioma Admin', 'email' => 'xavier.y@example.org'],
                ],
                default => [],
            };

            $logins = array_map(fn (array $row) => [...$row, 'password' => $password], $staff);
            $logins[] = [
                'label' => 'Member',
                'hint' => 'Member portal',
                'email' => 'member1.'.$tenant->slug.'@coopos.test',
                'password' => $password,
            ];

            return $logins;
        }

        return [
            ['label' => 'Super Admin', 'hint' => 'Platform', 'email' => 'nathan.k@example.net', 'password' => $password],
            ['label' => 'Ijebu staff', 'hint' => 'Conventional', 'email' => 'ursula.b@example.com', 'password' => $password],
            ['label' => 'Al-Barakah staff', 'hint' => 'Islamic', 'email' => 'xena.w@example.org', 'password' => $password],
            ['label' => 'Hybrid staff', 'hint' => 'Traders', 'email' => 'xavier.y@example.org', 'password' => $password],
            ['label' => 'Member', 'hint' => 'Al-Barakah portal', 'email' => 'member1.barakah@coopos.test', 'password' => $password],
        ];
    }
}
