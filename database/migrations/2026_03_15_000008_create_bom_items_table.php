<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->enum('line_type', ['part', 'operation'])->default('part');
            $table->foreignId('component_part_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
    }
};
