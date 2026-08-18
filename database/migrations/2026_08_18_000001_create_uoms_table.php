<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();
        });

        $now = now();

        DB::table('uoms')->insert(
            collect([
                ['code' => 'PCS', 'name' => 'Pieces'],
                ['code' => 'KG', 'name' => 'Kilogram'],
                ['code' => 'GRAM', 'name' => 'Gram'],
                ['code' => 'LITER', 'name' => 'Liter'],
                ['code' => 'METER', 'name' => 'Meter'],
                ['code' => 'BOX', 'name' => 'Box'],
                ['code' => 'ROLL', 'name' => 'Roll'],
                ['code' => 'SET', 'name' => 'Set'],
                ['code' => 'UNIT', 'name' => 'Unit'],
            ])->map(fn (array $uom): array => [...$uom, 'created_at' => $now, 'updated_at' => $now])->all()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uoms');
    }
};
