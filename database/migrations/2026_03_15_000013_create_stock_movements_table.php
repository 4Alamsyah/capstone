<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('work_order_report_id')->nullable()->constrained('work_order_reports')->nullOnDelete();
            $table->string('movement_type', 50);
            $table->decimal('quantity_change', 15, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
