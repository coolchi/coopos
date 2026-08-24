<?php

use App\Domain\Platform\TenantProvisioner;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $provisioner = app(TenantProvisioner::class);

        Tenant::query()->each(function (Tenant $tenant) use ($provisioner) {
            $provisioner->seedRoles($tenant);
        });

        TenantContext::forget();
    }
};
