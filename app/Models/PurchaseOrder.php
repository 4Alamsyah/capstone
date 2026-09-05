<?php

namespace App\Models;

use App\Services\WoNumberService;
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
        'quo_no',
        'supplier_id',
        'created_by',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'order_date',
        'expected_date',
        'currency_code',
        'term_payment',
        'department',
        'subtotal',
        'discount',
        'tax_amount',
        'notes',
        'approval_notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * User who created (prepared) this purchase order.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Management user who approved this purchase order.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Grand total = subtotal - discount + tax.
     */
    public function getGrandTotalAttribute(): float
    {
        return round((float) $this->subtotal - (float) $this->discount + (float) $this->tax_amount, 2);
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

    public function apInvoices(): HasMany
    {
        return $this->hasMany(ApInvoice::class);
    }

    public static function generateNumber(): string
    {
        $format = json_decode(AppSetting::get('po_format', ''), true) ?? self::defaultFormat();
        $stem = WoNumberService::stem($format);
        $separator = $format['separator'] ?? '-';
        $pattern = ($stem ? $stem.$separator : '').'%';

        $count = static::where('po_number', 'like', $pattern)->count();

        return WoNumberService::generate($format, $count + 1);
    }

    private static function defaultFormat(): array
    {
        return [
            'prefix' => 'PO',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ];
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
