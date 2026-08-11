<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultFormat = json_encode([
            'prefix' => 'MO',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ]);

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'wo_format'],
            ['value' => $defaultFormat, 'updated_at' => now()]
        );

        // Optional: Delete old wo_prefix if exists
        DB::table('app_settings')->where('key', 'wo_prefix')->delete();
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'wo_format')->delete();
    }
};
