<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `carrying_exchange_rate` is the exchange rate (to base currency) at
     * which the document's outstanding balance is currently carried in the
     * GL. It starts as the rate at billing/approval time and is updated by
     * each FX revaluation run, so realized gain/loss at payment time and the
     * next revaluation both measure movement only since the last checkpoint
     * instead of double-counting against the original invoice-date rate.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('carrying_exchange_rate', 20, 6)->nullable()->after('currency_code');
        });

        Schema::table('ap_invoices', function (Blueprint $table): void {
            $table->decimal('carrying_exchange_rate', 20, 6)->nullable()->after('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('carrying_exchange_rate');
        });

        Schema::table('ap_invoices', function (Blueprint $table): void {
            $table->dropColumn('carrying_exchange_rate');
        });
    }
};
