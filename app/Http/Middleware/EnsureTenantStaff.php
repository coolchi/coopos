<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->isPlatformAdmin() && session('impersonate_tenant_id')) {
            return $next($request);
        }

        abort_unless($user->tenant_id === tenant()?->id, 403);
        abort_if($user->type === UserType::Member, 403, 'Members should use the member portal.');

        if ($user->isBranchScoped() && $user->branch) {
            BranchContext::set($user->branch);
        }

        return $next($request);
    }
}
