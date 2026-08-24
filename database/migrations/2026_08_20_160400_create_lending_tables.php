<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('minimum_amount_kobo');
            $table->unsignedBigInteger('maximum_amount_kobo');
            $table->unsignedSmallInteger('minimum_tenure_months')->default(1);
            $table->unsignedSmallInteger('maximum_tenure_months')->default(12);
            $table->string('interest_method');
            $table->unsignedInteger('interest_rate_bps');
            $table->unsignedInteger('processing_fee_kobo')->default(0);
            $table->unsignedInteger('insurance_fee_kobo')->default(0);
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->string('repayment_frequency')->default('monthly');
            $table->unsignedInteger('penalty_rate_bps')->default(0);
            $table->unsignedTinyInteger('guarantors_required')->default(0);
            $table->boolean('collateral_required')->default(false);
            $table->json('eligibility')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->unsignedSmallInteger('tenure_months');
            $table->unsignedBigInteger('estimated_repayment_kobo')->nullable();
            $table->string('purpose')->nullable();
            $table->string('status')->default('draft');
            $table->json('eligibility_snapshot')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_number');
            $table->unsignedBigInteger('principal_kobo');
            $table->unsignedBigInteger('interest_kobo')->default(0);
            $table->unsignedBigInteger('fees_kobo')->default(0);
            $table->unsignedBigInteger('total_repayable_kobo');
            $table->unsignedBigInteger('outstanding_kobo');
            $table->unsignedSmallInteger('tenure_months');
            $table->string('interest_method');
            $table->unsignedInteger('interest_rate_bps');
            $table->string('repayment_frequency');
            $table->string('status')->default('approved');
            $table->date('disbursed_on')->nullable();
            $table->date('maturity_on')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'account_number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_on');
            $table->unsignedBigInteger('principal_due_kobo');
            $table->unsignedBigInteger('interest_due_kobo');
            $table->unsignedBigInteger('fees_due_kobo')->default(0);
            $table->unsignedBigInteger('penalty_due_kobo')->default(0);
            $table->unsignedBigInteger('amount_due_kobo');
            $table->unsignedBigInteger('amount_paid_kobo')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['tenant_id', 'due_on', 'status']);
        });

        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guarantor_member_id')->constrained('members')->cascadeOnDelete();
            $table->unsignedBigInteger('exposure_kobo');
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamps();

            $table->unique(['loan_application_id', 'guarantor_member_id'], 'loan_guarantor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_applications');
        Schema::dropIfExists('loan_products');
    }
};
