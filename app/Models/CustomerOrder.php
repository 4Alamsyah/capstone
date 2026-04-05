<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerOrder extends Model
{
    public const STATUS_QUOTATION = 0;

    public const STATUS_REGISTERED = 1;

    public const STATUS_CONFIRMED = 2;

    public const STATUS_PICKING = 3;

    public const STATUS_DELIVERED = 4;

    public const STATUS_HISTORICAL = 9;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'co_number',
        'customer_id',
        'status',
        'order_date',
        'delivery_date',
        'shipping_address',
        'payment_terms',
        'project_code',
        'delivery_type',
        'po_number',
        'currency_code',
        'subtotal',
        'needs_mo_suggestion',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'needs_mo_suggestion' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerOrderItem::class);
    }

    public static function generateNumber(): string
    {
        $prefix = AppSetting::get('co_prefix', 'CO');
        $yearMonth = now()->format('Ym');

        $pattern = "{$prefix}-{$yearMonth}-%";
        $count = static::where('co_number', 'like', $pattern)->count();
        $seq = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$yearMonth}-{$seq}";
    }

    public static function generateQuotationNumber(): string
    {
        $prefix = AppSetting::get('quotation_prefix', 'QT');
        $yearMonth = now()->format('Ym');

        $pattern = "{$prefix}-{$yearMonth}-%";
        $count = static::where('co_number', 'like', $pattern)->count();
        $seq = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$yearMonth}-{$seq}";
    }

    /**
     * @return array<int, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_QUOTATION => 'Quotation',
            self::STATUS_REGISTERED => 'Registered',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_PICKING => 'Picking',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_HISTORICAL => 'Historical',
        ];
    }
}
