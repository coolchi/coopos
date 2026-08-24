<?php

namespace App\Support;

use App\Models\Tenant;
use RuntimeException;

final class TenantContext
{
    private static ?Tenant $tenant = null;

    private static bool $bypassed = false;

    public static function set(?Tenant $tenant): void
    {
        BranchContext::forget();
        FiscalYearContext::forget();
        self::$tenant = $tenant;
        self::$bypassed = false;

        if ($tenant) {
            app()->instance('currentTenant', $tenant);
            setPermissionsTeamId($tenant->id);
        } else {
            app()->forgetInstance('currentTenant');
            setPermissionsTeamId(null);
        }
    }

    public static function current(): ?Tenant
    {
        return self::$tenant;
    }

    public static function require(): Tenant
    {
        if (! self::$tenant) {
            throw new RuntimeException('No tenant is bound to the current request.');
        }

        return self::$tenant;
    }

    public static function id(): ?int
    {
        return self::$tenant?->id;
    }

    public static function bypass(): void
    {
        self::$bypassed = true;
    }

    public static function isBypassed(): bool
    {
        return self::$bypassed;
    }

    public static function forget(): void
    {
        self::$tenant = null;
        self::$bypassed = false;
        app()->forgetInstance('currentTenant');
        setPermissionsTeamId(null);
        BranchContext::forget();
        FiscalYearContext::forget();
    }
}
