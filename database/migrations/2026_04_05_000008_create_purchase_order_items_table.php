<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 20)->default('PCS');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->decimal('received_quantity', 15, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
