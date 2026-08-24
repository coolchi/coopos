<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('type');
            $table->string('description')->nullable()->after('sort_order');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'branch_id', 'posted_at']);
        });

        Schema::create('accounting_vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('reference')->unique();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('contra_account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->date('occurred_on');
            $table->string('payee')->nullable();
            $table->string('description');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_vouchers');

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'branch_id', 'posted_at']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'description']);
        });
    }
};
