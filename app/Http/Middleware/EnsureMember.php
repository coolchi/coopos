<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Support\WrongTenantRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->tenant_id !== tenant()?->id) {
            return WrongTenantRedirect::for($request, $user);
        }

        abort_unless($user->type === UserType::Member || $user->member, 403);

        return $next($request);
    }
}
