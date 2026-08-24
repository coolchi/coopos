<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('membership_number');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('gender', 16)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('pending');
            $table->string('category')->nullable();
            $table->string('branch')->nullable();
            $table->string('group_name')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer')->nullable();
            $table->string('employment_id')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('joined_on')->nullable();
            $table->unsignedInteger('membership_fee_kobo')->default(0);
            $table->foreignId('sponsor_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('invite_token')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'membership_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
        });

        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->text('residential_address')->nullable();
            $table->string('state')->nullable();
            $table->string('lga')->nullable();
            $table->text('bvn_encrypted')->nullable();
            $table->text('nin_encrypted')->nullable();
            $table->timestamps();

            $table->unique('member_id');
        });

        Schema::create('member_next_of_kin', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->unsignedSmallInteger('allocation_percent')->default(100);
            $table->timestamps();

            $table->index(['tenant_id', 'member_id']);
        });

        Schema::create('member_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('purpose')->default('savings');
            $table->unsignedSmallInteger('allocation_percent')->default(100);
            $table->timestamps();
        });

        Schema::create('member_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('bank_code', 16)->nullable();
            $table->string('account_number', 20);
            $table->string('account_name');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'member_id']);
        });

        Schema::create('member_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('name');
            $table->string('path');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_documents');
        Schema::dropIfExists('member_bank_accounts');
        Schema::dropIfExists('member_beneficiaries');
        Schema::dropIfExists('member_next_of_kin');
        Schema::dropIfExists('member_profiles');
        Schema::dropIfExists('members');
    }
};
