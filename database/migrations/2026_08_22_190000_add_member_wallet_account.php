<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $exists = DB::table('accounts')
                ->where('tenant_id', $tenantId)
                ->where('code', '2110')
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('accounts')->insert([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'code' => '2110',
                'name' => 'Member Wallet',
                'type' => 'liability',
                'sort_order' => 15,
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('code', '2110')->delete();
    }
};
