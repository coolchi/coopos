<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Tenant;
use App\Support\BranchContext;
use App\Support\FiscalPeriod;
use App\Support\FiscalYearContext;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        $tenant = $request->attributes->get('tenant')
            ?? TenantContext::current()
            ?? Tenant::query()->where('slug', $slug)->firstOrFail();

        abort_unless($tenant->slug === $slug, 404);

        TenantContext::set($tenant);
        $request->attributes->set('tenant', $tenant);
        view()->share('tenant', $tenant);

        $user = $request->user();
        if ($user?->isCentralAdmin() && $request->hasSession()) {
            $uuid = $request->session()->get('branch_context_uuid');
            if (is_string($uuid) && $uuid !== '') {
                $branch = Branch::query()->where('uuid', $uuid)->where('is_active', true)->first();
                if ($branch) {
                    BranchContext::set($branch);
                }
            }
        }

        $sessionYear = $request->hasSession()
            ? $request->session()->get('fiscal_year_start_year')
            : null;
        $year = FiscalYearContext::clamp(is_numeric($sessionYear) ? (int) $sessionYear : null);
        FiscalYearContext::set($year);
        if ($request->hasSession() && (int) $sessionYear !== $year) {
            $request->session()->put('fiscal_year_start_year', $year);
        }

        return $next($request);
    }
}
