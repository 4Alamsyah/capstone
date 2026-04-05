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
        Schema::table('parts', function (Blueprint $table): void {
            $table->unsignedInteger('safety_stock')->default(0)->after('selling_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table): void {
            $table->dropColumn('safety_stock');
        });
    }
};
