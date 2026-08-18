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
        Schema::table('bom_items', function (Blueprint $table) {
            $table->foreignId('uom_id')->nullable()->after('work_center_id')->constrained('uoms')->nullOnDelete();
        });

        // Operation lines don't consume/produce a quantity of anything, so
        // quantity is only meaningful (and required) for 'part' lines.
        Schema::table('bom_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->nullable(false)->default(1)->change();
        });

        Schema::table('bom_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uom_id');
        });
    }
};
