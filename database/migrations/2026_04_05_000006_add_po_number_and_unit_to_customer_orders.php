<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->string('po_number', 100)->nullable()->after('delivery_type');
        });

        Schema::table('customer_order_items', function (Blueprint $table): void {
            $table->string('unit', 20)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('customer_order_items', function (Blueprint $table): void {
            $table->dropColumn('unit');
        });

        Schema::table('customer_orders', function (Blueprint $table): void {
            $table->dropColumn('po_number');
        });
    }
};
