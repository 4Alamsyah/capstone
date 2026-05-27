<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('purchase_voucher_item_id')
                ->nullable()
                ->after('remarks')
                ->constrained('purchase_voucher_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['purchase_voucher_item_id']);
            $table->dropColumn('purchase_voucher_item_id');
        });
    }
};
