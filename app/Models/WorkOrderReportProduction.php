<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderReportProduction extends Model
{
    protected $fillable = [
        'work_order_report_id',
        'work_order_id',
        'bom_item_id',
        'part_id',
        'bom_id',
        'warehouse_id',
        'good_quantity',
        'reject_quantity',
    ];

    protected $casts = [
        'good_quantity' => 'decimal:4',
        'reject_quantity' => 'decimal:4',
    ];

    public function workOrderReport(): BelongsTo
    {
        return $this->belongsTo(WorkOrderReport::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(BomItem::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
