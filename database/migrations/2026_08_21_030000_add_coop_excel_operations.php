<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_products', function (Blueprint $table) {
            $table->string('bucket')->default('savings')->after('name');
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('held_on');
            $table->string('kind')->default('general');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'held_on']);
        });

        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->boolean('present')->default(true);
            $table->timestamps();

            $table->unique(['meeting_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendances');
        Schema::dropIfExists('meetings');
        Schema::table('contribution_products', function (Blueprint $table) {
            $table->dropColumn('bucket');
        });
    }
};
