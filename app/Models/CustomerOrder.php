<?php

namespace App\Models;

use App\Services\WoNumberService;
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
        'quotation_number',
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
        $format = json_decode(AppSetting::get('co_format', ''), true) ?? self::defaultCoFormat();
        $stem = WoNumberService::stem($format);
        $separator = $format['separator'] ?? '-';
        $pattern = ($stem ? $stem . $separator : '') . '%';

        $existing = static::where('co_number', 'like', $pattern)->pluck('co_number');
        $sequence = WoNumberService::nextSequenceNumber($stem, $separator, $existing);

        return WoNumberService::generate($format, $sequence);
    }

    public static function generateQuotationNumber(): string
    {
        $format = json_decode(AppSetting::get('quotation_format', ''), true) ?? self::defaultQuotationFormat();
        $stem = WoNumberService::stem($format);
        $separator = $format['separator'] ?? '-';
        $pattern = ($stem ? $stem . $separator : '') . '%';

        $existing = static::where('co_number', 'like', $pattern)->pluck('co_number');
        $sequence = WoNumberService::nextSequenceNumber($stem, $separator, $existing);

        return WoNumberService::generate($format, $sequence);
    }

    public static function generateProjectCode(): string
    {
        $format = json_decode(AppSetting::get('project_format', ''), true) ?? self::defaultProjectFormat();
        $stem = WoNumberService::stem($format);
        $separator = $format['separator'] ?? '-';
        $pattern = ($stem ? $stem . $separator : '') . '%';

        $existing = static::where('project_code', 'like', $pattern)->pluck('project_code');
        $sequence = WoNumberService::nextSequenceNumber($stem, $separator, $existing);

        return WoNumberService::generate($format, $sequence);
    }

    private static function defaultProjectFormat(): array
    {
        return [
            'prefix' => 'PRJ',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ];
    }

    private static function defaultCoFormat(): array
    {
        return [
            'prefix' => 'CO',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ];
    }

    private static function defaultQuotationFormat(): array
    {
        return [
            'prefix' => 'QT',
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
            self::STATUS_QUOTATION => 'Quotation',
            self::STATUS_REGISTERED => 'Registered',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_PICKING => 'Picking',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_HISTORICAL => 'Historical',
        ];
    }
}
