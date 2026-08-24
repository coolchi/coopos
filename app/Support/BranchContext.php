<?php

namespace App\Support;

use App\Models\Branch;

final class BranchContext
{
    private static ?Branch $branch = null;

    private static bool $bypassed = false;

    public static function set(?Branch $branch): void
    {
        self::$branch = $branch;
        self::$bypassed = false;

        if ($branch) {
            app()->instance('currentBranch', $branch);
        } else {
            app()->forgetInstance('currentBranch');
        }
    }

    public static function current(): ?Branch
    {
        return self::$branch;
    }

    public static function id(): ?int
    {
        return self::$branch?->id;
    }

    public static function bypass(): void
    {
        self::$bypassed = true;
        self::$branch = null;
        app()->forgetInstance('currentBranch');
    }

    public static function isBypassed(): bool
    {
        return self::$bypassed;
    }

    public static function isConstrained(): bool
    {
        return self::$branch !== null && ! self::$bypassed;
    }

    public static function forget(): void
    {
        self::$branch = null;
        self::$bypassed = false;
        app()->forgetInstance('currentBranch');
    }
}
