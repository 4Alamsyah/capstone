<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_arrivals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('arrival_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_arrivals');
    }
};
