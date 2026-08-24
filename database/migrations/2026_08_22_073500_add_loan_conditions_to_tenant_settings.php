<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('loan_min_savings_percent')->default(50)->after('max_debt_to_savings_bps');
            $table->unsignedTinyInteger('loan_min_shares_percent')->default(10)->after('loan_min_savings_percent');
            $table->boolean('loan_require_registration')->default(true)->after('loan_min_shares_percent');
            $table->boolean('loan_require_foundation')->default(true)->after('loan_require_registration');
            $table->boolean('loan_require_agm')->default(true)->after('loan_require_foundation');
            $table->boolean('loan_require_dev_levy')->default(true)->after('loan_require_agm');
            $table->boolean('loan_check_attendance')->default(true)->after('loan_require_dev_levy');
            $table->unsignedTinyInteger('loan_max_absences_per_year')->default(3)->after('loan_check_attendance');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'loan_min_savings_percent',
                'loan_min_shares_percent',
                'loan_require_registration',
                'loan_require_foundation',
                'loan_require_agm',
                'loan_require_dev_levy',
                'loan_check_attendance',
                'loan_max_absences_per_year',
            ]);
        });
    }
};
