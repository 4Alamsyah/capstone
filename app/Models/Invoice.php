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

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'customer_order_id',
        'status',
        'invoice_date',
        'due_date',
        'currency_code',
        'subtotal',
        'tax_amount',
        'total_amount',
        'notes',
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
}
