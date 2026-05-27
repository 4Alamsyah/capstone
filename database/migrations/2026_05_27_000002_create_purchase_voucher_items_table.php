<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_voucher_id')->constrained('purchase_vouchers')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 20)->default('PCS');
            $table->decimal('stock_on_hand', 15, 4)->default(0); // snapshot at time of creation
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_voucher_items');
    }
};
