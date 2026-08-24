<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_products', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->after('is_active');
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->date('for_month')->nullable()->after('paid_on');
            $table->index(['tenant_id', 'for_month']);
        });

        if (Schema::hasColumn('contribution_products', 'bucket')) {
            DB::table('contribution_products')
                ->whereIn('bucket', ['savings', 'share', 'registration_fee'])
                ->update(['is_required' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'for_month']);
            $table->dropColumn('for_month');
        });

        Schema::table('contribution_products', function (Blueprint $table) {
            $table->dropColumn('is_required');
        });
    }
};
