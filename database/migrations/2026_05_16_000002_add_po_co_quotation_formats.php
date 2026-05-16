<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $poFormat = json_encode([
            'prefix' => 'PO',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ]);

        $coFormat = json_encode([
            'prefix' => 'CO',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ]);

        $quotationFormat = json_encode([
            'prefix' => 'QT',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ]);

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'po_format'],
            ['value' => $poFormat, 'updated_at' => now()]
        );

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'co_format'],
            ['value' => $coFormat, 'updated_at' => now()]
        );

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'quotation_format'],
            ['value' => $quotationFormat, 'updated_at' => now()]
        );

        // Hapus key lama jika ada
        DB::table('app_settings')->whereIn('key', ['po_prefix', 'co_prefix', 'quotation_prefix'])->delete();
    }

    public function down(): void
    {
        DB::table('app_settings')->whereIn('key', ['po_format', 'co_format', 'quotation_format'])->delete();
    }
};
