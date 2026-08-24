<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('app_mode', 16)->default('demo')->after('currency');
        });

        Schema::create('demo_seed_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 40);
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->timestamps();

            $table->index(['tenant_id', 'feature']);
            $table->index(['tenant_id', 'model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_seed_records');

        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('app_mode');
        });
    }
};
