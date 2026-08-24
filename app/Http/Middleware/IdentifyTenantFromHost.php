<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\BranchContext;
use App\Support\FiscalYearContext;
use App\Support\Tenancy;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantFromHost
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantContext::forget();
        BranchContext::forget();
        FiscalYearContext::forget();

        if (
            $request->isMethodSafe()
            && Tenancy::subdomainsEnabled()
            && Tenancy::isNamedCentralHost($request->getHost())
            && ! $request->expectsJson()
        ) {
            $first = explode('/', ltrim($request->decodedPath(), '/'))[0] ?? '';

            if ($first && ! in_array($first, config('coopos.reserved_slugs', []), true)) {
                $tenant = Tenant::query()->where('slug', $first)->first();

                if ($tenant) {
                    $rest = Str::after($request->decodedPath(), $first);

                    return redirect()->away(Tenancy::url($tenant, $rest !== '' ? $rest : '/dashboard'));
                }
            }
        }

        $tenant = Tenancy::tenantFromHost($request->getHost());

        if ($tenant) {
            TenantContext::set($tenant);
            $request->attributes->set('tenant', $tenant);
            $request->attributes->set('tenant_from_subdomain', true);
            view()->share('tenant', $tenant);
        }

        return $next($request);
    }
}
