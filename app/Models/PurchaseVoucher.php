<?php

namespace App\Models;

use App\Services\WoNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseVoucher extends Model
{
    public const STATUS_DRAFT     = 1;
    public const STATUS_SUBMITTED = 2;
    public const STATUS_APPROVED  = 3;
    public const STATUS_CONVERTED = 4;
    public const STATUS_REJECTED  = 8;
    public const STATUS_CANCELLED = 9;

    public const SOURCE_MANUAL             = 'manual';
    public const SOURCE_CO_CONFIRMATION    = 'co_confirmation';
    public const SOURCE_STOCK_RECOMMENDATION = 'stock_recommendation';

    protected $fillable = [
        'pv_number', 'status', 'source',
        'customer_order_id', 'created_by',
        'submitted_by', 'submitted_at',
        'approved_by', 'approved_at',
        'rejected_by', 'rejected_at',
        'approval_notes', 'required_date', 'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'rejected_at'  => 'datetime',
        'required_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseVoucherItem::class);
    }

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateNumber(): string
    {
        $format = json_decode(AppSetting::get('pv_format', ''), true) ?? self::defaultFormat();
        $stem = WoNumberService::stem($format);
        $separator = $format['separator'] ?? '-';
        $pattern = ($stem ? $stem . $separator : '') . '%';

        $count = static::where('pv_number', 'like', $pattern)->count();

        return WoNumberService::generate($format, $count + 1);
    }

    private static function defaultFormat(): array
    {
        return [
            'prefix'     => 'PV',
            'separator'  => '-',
            'components' => [
                ['type' => 'prefix',     'format' => 'raw'],
                ['type' => 'year',       'format' => 'YYYY'],
                ['type' => 'month',      'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_APPROVED  => 'Approved',
            self::STATUS_CONVERTED => 'Converted to PO',
            self::STATUS_REJECTED  => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
