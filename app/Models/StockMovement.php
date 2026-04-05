<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'part_id',
        'warehouse_id',
        'work_order_id',
        'work_order_report_id',
        'movement_type',
        'quantity_change',
        'notes',
    ];

    protected $casts = [
        'quantity_change' => 'decimal:4',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function workOrderReport(): BelongsTo
    {
        return $this->belongsTo(WorkOrderReport::class);
    }
}
