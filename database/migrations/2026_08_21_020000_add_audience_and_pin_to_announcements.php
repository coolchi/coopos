<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('audience')->default('all')->after('body');
            $table->foreignId('branch_id')->nullable()->after('audience')->constrained()->nullOnDelete();
            $table->boolean('is_pinned')->default(false)->after('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['audience', 'is_pinned']);
        });
    }
};
