<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 100);
            $table->string('symbol', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        DB::table('currencies')->insert([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'is_active' => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
