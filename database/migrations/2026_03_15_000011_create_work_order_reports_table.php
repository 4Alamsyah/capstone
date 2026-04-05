<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->decimal('good_quantity', 15, 4)->default(0);
            $table->decimal('reject_quantity', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_reports');
    }
};
