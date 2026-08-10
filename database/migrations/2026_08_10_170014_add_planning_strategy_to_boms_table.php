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
        Schema::table('boms', function (Blueprint $table): void {
            $table->string('planning_strategy', 20)->default('order_oriented')->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boms', function (Blueprint $table): void {
            $table->dropColumn('planning_strategy');
        });
    }
};
