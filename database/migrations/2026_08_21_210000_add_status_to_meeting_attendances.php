<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_attendances', function (Blueprint $table) {
            $table->string('status', 20)->default('absent')->after('present');
        });

        DB::table('meeting_attendances')->where('present', true)->update(['status' => 'present']);
        DB::table('meeting_attendances')->where('present', false)->update(['status' => 'absent']);
    }

    public function down(): void
    {
        Schema::table('meeting_attendances', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
