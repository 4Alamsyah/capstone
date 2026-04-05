<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    public const STATUS_REGISTERED = 1;

    public const STATUS_PARTIAL = 2;

    public const STATUS_COMPLETED = 3;

    public const STATUS_CANCELLED = 9;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'status',
        'order_date',
        'expected_date',
        'currency_code',
        'subtotal',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function arrivals(): HasMany
    {
        return $this->hasMany(PurchaseArrival::class);
    }

    public static function generateNumber(): string
    {
        $prefix = AppSetting::get('po_prefix', 'PO');
        $yearMonth = now()->format('Ym');

        $pattern = "{$prefix}-{$yearMonth}-%";
        $count = static::where('po_number', 'like', $pattern)->count();
        $seq = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$yearMonth}-{$seq}";
    }

    /**
     * @return array<int, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_REGISTERED => 'Registered',
            self::STATUS_PARTIAL => 'Partial Arrival',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
