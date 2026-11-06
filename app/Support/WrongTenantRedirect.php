<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The session cookie is shared across *.coopos.ng (SESSION_DOMAIN=.coopos.ng),
 * so a user signed in on the platform or another cooperative can land on a
 * tenant they don't belong to. Instead of a bare 403, send them somewhere useful.
 */
class WrongTenantRedirect
{
    public static function for(Request $request, User $user): Response
    {
        $message = 'You are signed in with an account that does not belong to this cooperative.';

        if ($user->isPlatformAdmin()) {
            if (! $request->isMethod('GET') || $request->expectsJson()) {
                abort(403, $message);
            }

            return redirect()->away($user->homeUrl())
                ->with('error', $message.' Use "Impersonate" on the cooperative to open its dashboard.');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(401, $message);
        }

        return redirect()->guest(url('/login'))
            ->with('status', $message.' Please sign in with your account for this cooperative.');
    }
}
