<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseArrival extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'reported_by',
        'arrival_date',
        'notes',
    ];

    protected $casts = [
        'arrival_date' => 'date',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseArrivalItem::class);
    }
}
