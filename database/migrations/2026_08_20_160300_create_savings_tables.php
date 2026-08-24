<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('finance_mode')->default('conventional');
            $table->string('frequency');
            $table->boolean('amount_is_fixed')->default(false);
            $table->unsignedBigInteger('minimum_amount_kobo')->default(0);
            $table->unsignedBigInteger('maximum_amount_kobo')->nullable();
            $table->unsignedBigInteger('default_amount_kobo')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->unsignedSmallInteger('notice_days')->default(0);
            $table->boolean('penalties_enabled')->default(false);
            $table->unsignedInteger('penalty_amount_kobo')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('eligibility')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('contribution_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contribution_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->string('status')->default('active');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'contribution_product_id', 'status'], 'contrib_enroll_member_product_status');
        });

        Schema::create('contribution_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contribution_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contribution_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on');
            $table->unsignedBigInteger('amount_kobo');
            $table->unsignedBigInteger('paid_kobo')->default(0);
            $table->unsignedBigInteger('penalty_kobo')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'contribution_product_id', 'due_on'], 'contrib_sched_member_product_due');
            $table->index(['tenant_id', 'due_on', 'status']);
        });

        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contribution_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('contribution_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->date('paid_on');
            $table->string('channel')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'paid_on']);
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contribution_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('savings');
            $table->unsignedBigInteger('amount_kobo');
            $table->unsignedBigInteger('fee_kobo')->default(0);
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('member_bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('contribution_schedules');
        Schema::dropIfExists('contribution_enrollments');
        Schema::dropIfExists('contribution_products');
    }
};
