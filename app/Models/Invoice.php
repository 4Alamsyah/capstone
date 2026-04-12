<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 1;

    public const STATUS_SENT = 2;

    public const STATUS_PAID = 3;

    public const STATUS_CANCELLED = 9;

    public const PAYMENT_APPROVAL_NOT_REQUESTED = 0;

    public const PAYMENT_APPROVAL_PENDING = 1;

    public const PAYMENT_APPROVAL_APPROVED = 2;

    public const PAYMENT_APPROVAL_REJECTED = 3;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'customer_order_id',
        'status',
        'payment_approval_status',
        'payment_requested_by',
        'payment_requested_at',
        'payment_approved_by',
        'payment_approved_at',
        'payment_rejected_by',
        'payment_rejected_at',
        'invoice_date',
        'due_date',
        'currency_code',
        'subtotal',
        'tax_amount',
        'total_amount',
        'notes',
        'payment_approval_notes',
        'paid_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_requested_at' => 'datetime',
        'payment_approved_at' => 'datetime',
        'payment_rejected_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public static function generateNumber(): string
    {
        $prefix = AppSetting::get('invoice_prefix', 'INV');
        $yearMonth = now()->format('Ym');

        $pattern = "{$prefix}-{$yearMonth}-%";
        $count = static::where('invoice_number', 'like', $pattern)->count();
        $seq = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$yearMonth}-{$seq}";
    }

    /**
     * @return array<int, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Sent',
            self::STATUS_PAID => 'Paid',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function paymentApprovalLabels(): array
    {
        return [
            self::PAYMENT_APPROVAL_NOT_REQUESTED => 'Not Requested',
            self::PAYMENT_APPROVAL_PENDING => 'Pending Approval',
            self::PAYMENT_APPROVAL_APPROVED => 'Approved',
            self::PAYMENT_APPROVAL_REJECTED => 'Rejected',
        ];
    }
}
