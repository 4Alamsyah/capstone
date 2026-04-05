<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->integer('stock_on_hand')->default(0);
            $table->boolean('requires_mo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_items');
    }
};
