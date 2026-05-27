<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseVoucherItem extends Model
{
    protected $fillable = [
        'purchase_voucher_id', 'part_id',
        'quantity', 'unit', 'stock_on_hand', 'remarks',
    ];

    protected $casts = [
        'quantity'     => 'decimal:4',
        'stock_on_hand' => 'decimal:4',
    ];

    public function purchaseVoucher(): BelongsTo
    {
        return $this->belongsTo(PurchaseVoucher::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
