<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keyword hints (EN + ID) used to best-effort classify existing free-text
     * `category` values into a structured `account_type` on backfill. Any
     * account_type left null after this needs to be classified manually via
     * the Chart of Accounts screen before it will appear on the Balance Sheet.
     */
    private const TYPE_KEYWORDS = [
        'asset' => ['asset', 'aset', 'aktiva'],
        'liability' => ['liabilit', 'kewajiban', 'hutang', 'utang', 'payable'],
        'equity' => ['equity', 'ekuitas', 'modal'],
        'revenue' => ['revenue', 'income', 'pendapatan', 'penjualan'],
        'expense' => ['expense', 'cost', 'beban', 'biaya', 'hpp'],
    ];

    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            $table->string('account_type')->nullable()->after('category')->index();
        });

        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            foreach ($keywords as $keyword) {
                DB::table('chart_of_accounts')
                    ->whereNull('account_type')
                    ->whereRaw('LOWER(category) LIKE ?', ["%{$keyword}%"])
                    ->update(['account_type' => $type]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            $table->dropColumn('account_type');
        });
    }
};
