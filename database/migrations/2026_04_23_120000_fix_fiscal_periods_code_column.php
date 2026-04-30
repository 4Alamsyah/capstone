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
        if (! Schema::hasColumn('fiscal_periods', 'code')) {
            Schema::table('fiscal_periods', function (Blueprint $table): void {
                $table->string('code')->nullable()->after('id');
            });

            if (Schema::hasColumn('fiscal_periods', 'start_date')) {
                DB::table('fiscal_periods')
                    ->whereNull('code')
                    ->update([
                        'code' => DB::raw("DATE_FORMAT(start_date, '%Y-%m')"),
                    ]);
            }

            DB::table('fiscal_periods')
                ->whereNull('code')
                ->update([
                    'code' => DB::raw("CONCAT('PERIOD-', id)"),
                ]);

            Schema::table('fiscal_periods', function (Blueprint $table): void {
                $table->unique('code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('fiscal_periods', 'code')) {
            Schema::table('fiscal_periods', function (Blueprint $table): void {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }
};
