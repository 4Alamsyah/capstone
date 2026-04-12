<?php

namespace App\Models;

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

    /**
     * Generate the next WO number.
     * Format: {PREFIX}-{YYYYMM}-{5-digit sequence for that year-month}
     * e.g., WO-202603-00001
     */
    public static function generateNumber(): string
    {
        $prefix = AppSetting::get('wo_prefix', 'WO');
        $yearMonth = now()->format('Ym'); // e.g. 202603

        // Count existing WOs in this year-month with this prefix pattern
        $pattern = "{$prefix}-{$yearMonth}-%";
        $count = static::where('wo_number', 'like', $pattern)->count();
        $seq = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$yearMonth}-{$seq}";
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
