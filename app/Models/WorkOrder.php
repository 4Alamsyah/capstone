<?php

namespace App\Models;

use App\Services\WoNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'wo_number',
        'bom_id',
        'purchase_order_id',
        'quantity',
        'status',
        'scheduled_date',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity'       => 'decimal:4',
        'scheduled_date' => 'date',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(WorkOrderReport::class)->latest();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkOrderLog::class)->latest();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    public static function generateNumber(): string
    {
        $format = json_decode(AppSetting::get('wo_format', ''), true) ?? self::defaultFormat();
        $stem = WoNumberService::stem($format);
        $separator = $format['separator'] ?? '-';
        $pattern = ($stem ? $stem . $separator : '') . '%';

        $count = static::where('wo_number', 'like', $pattern)->count();

        return WoNumberService::generate($format, $count + 1);
    }

    private static function defaultFormat(): array
    {
        return [
            'prefix' => 'WO',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ];
    }

    /**
     * Status label map.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'draft'       => 'Draft',
            'released'    => 'Released',
            'in_progress' => 'In Progress',
            'completed'   => 'Completed',
            'cancelled'   => 'Cancelled',
        ];
    }
}
