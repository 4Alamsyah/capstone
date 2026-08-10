<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_order_report_productions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_report_id')->constrained('work_order_reports')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('bom_item_id')->nullable()->constrained('bom_items')->nullOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('bom_id')->nullable()->constrained('boms')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('good_quantity', 15, 4)->default(0);
            $table->decimal('reject_quantity', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_report_productions');
    }
};
