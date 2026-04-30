<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('chart_of_accounts', 'code')) {
            Schema::table('chart_of_accounts', function (Blueprint $table): void {
                $table->string('code')->nullable()->after('id');
            });

            DB::table('chart_of_accounts')
                ->whereNull('code')
                ->update([
                    'code' => DB::raw("CONCAT('ACC-', LPAD(id, 4, '0'))"),
                ]);
        }

        if (! Schema::hasColumn('chart_of_accounts', 'name')) {
            Schema::table('chart_of_accounts', function (Blueprint $table): void {
                $table->string('name')->nullable()->after('code');
            });

            DB::table('chart_of_accounts')
                ->whereNull('name')
                ->update([
                    'name' => DB::raw("CONCAT('Account ', id)"),
                ]);
        }

        if (! Schema::hasColumn('chart_of_accounts', 'category')) {
            Schema::table('chart_of_accounts', function (Blueprint $table): void {
                $table->string('category')->nullable()->after('name');
            });

            DB::table('chart_of_accounts')
                ->whereNull('category')
                ->update([
                    'category' => 'Uncategorized',
                ]);
        }

        if (! Schema::hasColumn('chart_of_accounts', 'status')) {
            Schema::table('chart_of_accounts', function (Blueprint $table): void {
                $table->string('status')->nullable()->after('category');
            });
        }

        DB::table('chart_of_accounts')
            ->whereNull('status')
            ->update([
                'status' => 'active',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a recovery migration; we intentionally keep data columns on rollback.
    }
};
