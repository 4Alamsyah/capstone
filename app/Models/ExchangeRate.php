<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency_code',
        'rate_to_base',
        'rate_date',
        'created_by',
    ];

    protected $casts = [
        'rate_to_base' => 'decimal:6',
        'rate_date' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve how many base-currency units 1 unit of `$currencyCode` is
     * worth as of `$date` (the latest rate on or before that date). Returns
     * 1.0 when `$currencyCode` already is the base currency.
     *
     * @throws ValidationException when a non-base currency has no rate on or before the date
     */
    public static function rateFor(string $currencyCode, string $baseCurrencyCode, string $date): float
    {
        $currencyCode = strtoupper($currencyCode);
        $baseCurrencyCode = strtoupper($baseCurrencyCode);

        if ($currencyCode === $baseCurrencyCode) {
            return 1.0;
        }

        $rate = static::query()
            ->where('currency_code', $currencyCode)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();

        if (! $rate) {
            throw ValidationException::withMessages([
                'currency_code' => "Belum ada exchange rate untuk {$currencyCode} ke {$baseCurrencyCode} pada atau sebelum tanggal {$date}. Tambahkan dulu di Accounting > Exchange Rates.",
            ]);
        }

        return (float) $rate->rate_to_base;
    }
}
