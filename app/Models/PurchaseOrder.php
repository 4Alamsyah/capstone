<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING_APPROVAL = 1;

    public const STATUS_APPROVED = 2;

    public const STATUS_PARTIAL = 3;

    public const STATUS_COMPLETED = 4;

    public const STATUS_REJECTED = 8;

    public const STATUS_CANCELLED = 9;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'order_date',
        'expected_date',
        'currency_code',
        'subtotal',
        'notes',
        'approval_notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
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

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
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
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PARTIAL => 'Partial Arrival',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
