<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('co_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->tinyInteger('status')->default(1)->index();
            $table->date('order_date');
            $table->date('delivery_date')->nullable()->index();
            $table->text('shipping_address')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('currency_code', 3)->default('IDR');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->boolean('needs_mo_suggestion')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_orders');
    }
};
