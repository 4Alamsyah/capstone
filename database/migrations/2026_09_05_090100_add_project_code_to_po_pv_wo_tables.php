<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('project_code', 100)->nullable()->after('po_number');
        });

        Schema::table('purchase_vouchers', function (Blueprint $table): void {
            $table->string('project_code', 100)->nullable()->after('pv_number');
        });

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->string('project_code', 100)->nullable()->after('wo_number');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn('project_code');
        });

        Schema::table('purchase_vouchers', function (Blueprint $table): void {
            $table->dropColumn('project_code');
        });

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn('project_code');
        });
    }
};
