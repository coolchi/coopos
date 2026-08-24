<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('monipay');
            $table->string('wallet_id')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_code', 16)->nullable();
            $table->string('status')->default('pending');
            $table->json('meta')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'member_id', 'provider']);
            $table->index(['provider', 'account_number']);
            $table->index(['provider', 'wallet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_virtual_accounts');
    }
};
