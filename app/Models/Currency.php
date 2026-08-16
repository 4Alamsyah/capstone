<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * The app-wide default currency's code + display symbol, resolved from
     * the `default_currency_code` AppSetting. Falls back to the code itself
     * when the currency has no symbol configured (e.g. a custom currency
     * added without one). Never pass this code to Intl.NumberFormat's
     * `currency` option on the frontend — custom currency codes added via
     * General Settings aren't guaranteed to be valid ISO 4217 codes, which
     * would throw a RangeError.
     *
     * @return array{code: string, symbol: string}
     */
    public static function currentDefault(): array
    {
        $code = (string) AppSetting::get('default_currency_code', 'IDR');
        $currency = static::query()->where('code', $code)->first();

        return [
            'code' => $code,
            'symbol' => $currency?->symbol ?: $code,
        ];
    }
}
