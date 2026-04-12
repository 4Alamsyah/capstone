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
        Schema::create('tool_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('borrower_name');
            $table->integer('borrowed_quantity');
            $table->integer('returned_quantity')->default(0);
            $table->dateTime('borrowed_at');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->string('status', 20)->default('borrowed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'borrowed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_loans');
    }
};
