<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->string('project_code', 100)->nullable()->after('payment_terms');
            $table->string('delivery_type', 20)->nullable()->after('project_code');
        });

        Schema::table('customer_order_items', function (Blueprint $table): void {
            $table->string('remarks', 255)->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_items', function (Blueprint $table): void {
            $table->dropColumn('remarks');
        });

        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->dropColumn(['project_code', 'delivery_type']);
        });
    }
};
