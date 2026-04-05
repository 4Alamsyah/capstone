<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_arrival_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_arrival_id')->constrained('purchase_arrivals')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_arrival_items');
    }
};
