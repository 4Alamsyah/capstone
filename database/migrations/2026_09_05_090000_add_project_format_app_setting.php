<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'project_format'],
            [
                'value' => json_encode([
                    'prefix'     => 'PRJ',
                    'separator'  => '-',
                    'components' => [
                        ['type' => 'prefix',     'format' => 'raw'],
                        ['type' => 'year',       'format' => 'YYYY'],
                        ['type' => 'month',      'format' => 'MM'],
                        ['type' => 'sequential', 'format' => '5'],
                    ],
                ]),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'project_format')->delete();
    }
};
