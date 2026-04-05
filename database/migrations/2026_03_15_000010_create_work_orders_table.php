<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('wo_number')->unique();
            $table->foreignId('bom_id')->constrained('boms')->restrictOnDelete();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->enum('status', ['draft', 'released', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->date('scheduled_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
