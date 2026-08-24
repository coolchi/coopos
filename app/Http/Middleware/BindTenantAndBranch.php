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

class BindTenantAndBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        if (is_string($slug) && $slug !== '') {
            $tenant = TenantContext::current();
            if (! $tenant || $tenant->slug !== $slug) {
                $tenant = Tenant::query()->where('slug', $slug)->first();
                if ($tenant) {
                    TenantContext::set($tenant);
                    $request->attributes->set('tenant', $tenant);
                }
            }
        }

        $user = $request->user();
        if ($user && TenantContext::id() && $user->tenant_id === TenantContext::id()) {
            setPermissionsTeamId($user->tenant_id);
            $this->bindBranch($request, $user);
            $this->bindFiscalYear($request);
        }

        return $next($request);
    }

    private function bindFiscalYear(Request $request): void
    {
        $sessionYear = $request->hasSession()
            ? $request->session()->get('fiscal_year_start_year')
            : null;

        $year = FiscalYearContext::clamp(is_numeric($sessionYear) ? (int) $sessionYear : null);
        FiscalYearContext::set($year);

        if ($request->hasSession() && (int) $sessionYear !== $year) {
            $request->session()->put('fiscal_year_start_year', $year);
        }
    }

    private function bindBranch(Request $request, $user): void
    {
        if (! $user->isCentralAdmin()) {
            if ($user->branch) {
                BranchContext::set($user->branch);
            }

            return;
        }

        $uuid = $request->hasSession() ? $request->session()->get('branch_context_uuid') : null;
        if (! is_string($uuid) || $uuid === '') {
            return;
        }

        $branch = Branch::query()
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->first();

        if ($branch) {
            BranchContext::set($branch);
        }
    }
}
