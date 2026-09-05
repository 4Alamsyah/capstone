<?php

namespace App\Models;

use App\Services\WoNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CHEQUE = 'cheque';

    public const METHOD_OTHER = 'other';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_number',
        'payable_type',
        'payable_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_number',
        'notes',
        'journal_entry_id',
        'recorded_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return array<int, string>
     */
    public static function methodLabels(): array
    {
        return [
            self::METHOD_CASH => 'Cash',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CHEQUE => 'Cheque',
            self::METHOD_OTHER => 'Other',
        ];
    }

    public static function generateNumber(): string
    {
        $prefix = AppSetting::get('payment_prefix', 'PAY');
        $yearMonth = now()->format('Ym');
        $stem = "{$prefix}-{$yearMonth}";

        $pattern = "{$stem}-%";
        $existing = static::where('payment_number', 'like', $pattern)->pluck('payment_number');
        $sequence = WoNumberService::nextSequenceNumber($stem, '-', $existing);
        $seq = str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

        return "{$stem}-{$seq}";
    }
}
