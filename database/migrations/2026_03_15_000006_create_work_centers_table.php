<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_centers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_per_operation', 15, 2)->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_centers');
    }
};
