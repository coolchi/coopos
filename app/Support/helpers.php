<?php

use App\Models\Tenant;
use App\Support\Money;
use App\Support\Tenancy;
use App\Support\TenantContext;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return TenantContext::current();
    }
}

if (! function_exists('tenant_or_fail')) {
    function tenant_or_fail(): Tenant
    {
        return TenantContext::require();
    }
}

if (! function_exists('money')) {
    function money(int $kobo, string $currency = 'NGN'): string
    {
        return Money::format($kobo, $currency);
    }
}

if (! function_exists('tenant_url')) {
    function tenant_url(?Tenant $tenant = null, string $path = '/'): string
    {
        $tenant ??= tenant_or_fail();

        return Tenancy::url($tenant, $path);
    }
}

if (! function_exists('tenant_path')) {
    function tenant_path(string $suffix = ''): string
    {
        $prefix = Tenancy::pathPrefix();
        $suffix = $suffix === '' ? '' : '/'.ltrim($suffix, '/');

        return $prefix.$suffix;
    }
}
if (! function_exists('naira_to_kobo')) {
    function naira_to_kobo(float|int|string $naira): int
    {
        return Money::fromNaira($naira)->kobo();
    }
}
