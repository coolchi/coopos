<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('islamic_financing_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('structure');
            $table->unsignedBigInteger('minimum_amount_kobo');
            $table->unsignedBigInteger('maximum_amount_kobo');
            $table->unsignedSmallInteger('minimum_tenure_months')->default(1);
            $table->unsignedSmallInteger('maximum_tenure_months')->default(12);
            $table->unsignedInteger('admin_fee_kobo')->default(0);
            $table->unsignedInteger('default_profit_bps')->nullable();
            $table->string('repayment_frequency')->default('monthly');
            $table->unsignedTinyInteger('guarantors_required')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('shariah_status')->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shariah_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('islamic_financing_product_id')->constrained()->cascadeOnDelete();
            $table->string('adviser_name');
            $table->string('status')->default('under_review');
            $table->date('approved_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();
        });

        Schema::create('qard_hasan_financings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('islamic_financing_product_id')->constrained()->restrictOnDelete();
            $table->string('account_number');
            $table->unsignedBigInteger('principal_kobo');
            $table->unsignedBigInteger('admin_fee_kobo')->default(0);
            $table->unsignedBigInteger('total_repayable_kobo');
            $table->unsignedBigInteger('outstanding_kobo');
            $table->unsignedSmallInteger('tenure_months');
            $table->string('status')->default('pending_approval');
            $table->string('purpose')->nullable();
            $table->date('disbursed_on')->nullable();
            $table->date('maturity_on')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'account_number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('qard_hasan_installments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qard_hasan_financing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_on');
            $table->unsignedBigInteger('amount_due_kobo');
            $table->unsignedBigInteger('amount_paid_kobo')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['qard_hasan_financing_id', 'installment_number'], 'qard_installment_unique');
        });

        Schema::create('murabaha_financings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('islamic_financing_product_id')->constrained()->restrictOnDelete();
            $table->string('account_number');
            $table->string('asset_description');
            $table->string('supplier')->nullable();
            $table->unsignedBigInteger('purchase_cost_kobo');
            $table->unsignedBigInteger('profit_kobo');
            $table->unsignedBigInteger('sale_price_kobo');
            $table->unsignedBigInteger('down_payment_kobo')->default(0);
            $table->unsignedBigInteger('outstanding_kobo');
            $table->unsignedSmallInteger('tenure_months');
            $table->string('status')->default('pending_approval');
            $table->string('invoice_path')->nullable();
            $table->date('agreement_on')->nullable();
            $table->date('disbursed_on')->nullable();
            $table->boolean('sale_frozen')->default(false);
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'account_number']);
        });

        Schema::create('murabaha_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('murabaha_financing_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('ownership_status')->default('cooperative');
            $table->timestamps();
        });

        Schema::create('murabaha_installments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('murabaha_financing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_on');
            $table->unsignedBigInteger('amount_due_kobo');
            $table->unsignedBigInteger('amount_paid_kobo')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['murabaha_financing_id', 'installment_number'], 'murabaha_installment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('murabaha_installments');
        Schema::dropIfExists('murabaha_assets');
        Schema::dropIfExists('murabaha_financings');
        Schema::dropIfExists('qard_hasan_installments');
        Schema::dropIfExists('qard_hasan_financings');
        Schema::dropIfExists('shariah_approvals');
        Schema::dropIfExists('islamic_financing_products');
    }
};
