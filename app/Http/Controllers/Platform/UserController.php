<?php

namespace App\Http\Controllers\Platform;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        TenantContext::bypass();

        $users = User::query()
            ->with('tenant')
            ->where('type', UserType::Platform)
            ->when($request->string('q')->toString(), function ($query, string $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($request->string('role')->toString(), function ($query, string $role) {
                if ($role === 'admin') {
                    $query->where('is_platform_admin', true);
                }
                if ($role === 'agent') {
                    $query->where('is_platform_admin', false);
                }
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->is_platform_admin ? 'admin' : 'agent',
                'cooperative' => $user->tenant?->name,
                'slug' => $user->tenant?->slug,
                'last_login_at' => $user->last_login_at?->diffForHumans() ?? 'Never',
            ]);

        return Inertia::render('Platform/Users', [
            'users' => $users,
            'filters' => $request->only(['q', 'role']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,agent'],
        ]);

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'type' => UserType::Platform,
            'is_platform_admin' => $data['role'] === 'admin',
            'email_verified_at' => now(),
        ]);

        return back()->with('success', $data['role'] === 'admin' ? 'Platform admin added.' : 'Platform agent added.');
    }
}
