<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_products', function (Blueprint $table) {
            $table->string('monipay_revenue_head_id')->nullable()->after('is_required');
            $table->string('monipay_revenue_head_name')->nullable()->after('monipay_revenue_head_id');
            $table->string('monipay_settlement_bank_id')->nullable()->after('monipay_revenue_head_name');
            $table->string('monipay_settlement_account_name')->nullable()->after('monipay_settlement_bank_id');
            $table->string('monipay_settlement_account_no', 20)->nullable()->after('monipay_settlement_account_name');
            $table->string('monipay_settlement_bank_name')->nullable()->after('monipay_settlement_account_no');
        });
    }

    public function down(): void
    {
        Schema::table('contribution_products', function (Blueprint $table) {
            $table->dropColumn([
                'monipay_revenue_head_id',
                'monipay_revenue_head_name',
                'monipay_settlement_bank_id',
                'monipay_settlement_account_name',
                'monipay_settlement_account_no',
                'monipay_settlement_bank_name',
            ]);
        });
    }
};
