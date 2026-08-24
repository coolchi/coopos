<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->boolean('is_headquarters')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->restrictOnDelete();
        });

        foreach ([
            'contributions',
            'payment_transactions',
            'financial_transactions',
            'withdrawal_requests',
            'loans',
            'loan_applications',
            'qard_hasan_financings',
            'murabaha_financings',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('branch_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
                    $blueprint->index(['tenant_id', 'branch_id']);
                });
            }
        }

        $this->backfillHeadquarters();

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('branch');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('branch')->nullable();
        });

        foreach ([
            'contributions',
            'payment_transactions',
            'financial_transactions',
            'withdrawal_requests',
            'loans',
            'loan_applications',
            'qard_hasan_financings',
            'murabaha_financings',
        ] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropConstrainedForeignId('branch_id');
                });
            }
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
    }

    private function backfillHeadquarters(): void
    {
        $tenants = DB::table('tenants')->whereNull('deleted_at')->get();

        foreach ($tenants as $tenant) {
            $hqId = DB::table('branches')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'CENTRAL BRANCH',
                'code' => 'HQ',
                'state' => $tenant->state,
                'city' => $tenant->city,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'is_headquarters' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('members')->where('tenant_id', $tenant->id)->whereNull('branch_id')->update(['branch_id' => $hqId]);

            foreach ([
                'contributions',
                'payment_transactions',
                'financial_transactions',
                'withdrawal_requests',
                'loans',
                'loan_applications',
                'qard_hasan_financings',
                'murabaha_financings',
            ] as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'member_id')) {
                    continue;
                }

                $rows = DB::table($table)->where('tenant_id', $tenant->id)->whereNull('branch_id')->get(['id', 'member_id']);
                $memberBranches = DB::table('members')->where('tenant_id', $tenant->id)->pluck('branch_id', 'id');

                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([
                        'branch_id' => $memberBranches[$row->member_id] ?? $hqId,
                    ]);
                }
            }
        }
    }
};
