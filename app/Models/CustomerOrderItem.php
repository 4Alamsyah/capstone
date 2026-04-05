<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOrderItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_order_id',
        'part_id',
        'quantity',
        'unit',
        'unit_price',
        'line_total',
        'remarks',
        'stock_on_hand',
        'requires_mo',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'requires_mo' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
