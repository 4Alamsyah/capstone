<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseArrivalItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_arrival_id',
        'purchase_order_item_id',
        'part_id',
        'warehouse_id',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function arrival(): BelongsTo
    {
        return $this->belongsTo(PurchaseArrival::class, 'purchase_arrival_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
