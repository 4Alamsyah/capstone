<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('log_type', 50);
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_logs');
    }
};
