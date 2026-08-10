<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderReport extends Model
{
    protected $fillable = [
        'work_order_id',
        'reported_by',
        'previous_status',
        'new_status',
        'good_quantity',
        'reject_quantity',
        'notes',
    ];

    protected $casts = [
        'good_quantity' => 'decimal:4',
        'reject_quantity' => 'decimal:4',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(WorkOrderReportProduction::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
