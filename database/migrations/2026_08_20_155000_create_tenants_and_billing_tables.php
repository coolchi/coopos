<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('monthly_price_kobo')->default(0);
            $table->unsignedInteger('yearly_price_kobo')->default(0);
            $table->unsignedInteger('member_limit')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('legal_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('type');
            $table->string('mode');
            $table->string('status')->default('pending');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('lga')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 16)->nullable();
            $table->string('custom_domain')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'mode']);
            $table->index('state');
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 8)->default('NGN');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->string('membership_number_prefix')->default('M');
            $table->unsignedInteger('membership_number_next')->default(1);
            $table->unsignedInteger('membership_fee_kobo')->default(0);
            $table->boolean('require_membership_approval')->default(true);
            $table->boolean('require_verified_bank_for_payout')->default(true);
            $table->unsignedInteger('guarantor_exposure_cap_kobo')->nullable();
            $table->unsignedInteger('max_active_loans')->default(1);
            $table->unsignedInteger('max_debt_to_savings_bps')->default(20000);
            $table->json('islamic_terminology')->nullable();
            $table->json('notification_channels')->nullable();
            $table->json('extras')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->string('interval')->default('monthly');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->string('paystack_authorization_code')->nullable();
            $table->string('paystack_customer_code')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->unsignedBigInteger('amount_kobo');
            $table->string('status')->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('subscription_plans');
    }
};
