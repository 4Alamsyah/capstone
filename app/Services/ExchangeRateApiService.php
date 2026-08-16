<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class ExchangeRateApiService
{
    private const API_URL = 'https://open.er-api.com/v6/latest/USD';

    /**
     * Fetch how many units of every currency 1 USD is worth right now.
     *
     * @return array<string, float>|null null on any network/API failure
     */
    public function fetchUsdRates(): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::API_URL);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        if (($data['result'] ?? null) !== 'success' || ! is_array($data['rates'] ?? null)) {
            return null;
        }

        return $data['rates'];
    }

    /**
     * Cross-rate: how many units of `$baseCurrencyCode` is 1 unit of
     * `$currencyCode` worth, derived from USD-anchored rates. Returns null
     * when either currency isn't present in the API response (e.g. a custom
     * currency code not recognized by the provider).
     *
     * @param  array<string, float>  $usdRates
     */
    public function rateToBase(array $usdRates, string $currencyCode, string $baseCurrencyCode): ?float
    {
        $currencyCode = strtoupper($currencyCode);
        $baseCurrencyCode = strtoupper($baseCurrencyCode);

        if (! isset($usdRates[$currencyCode]) || ! isset($usdRates[$baseCurrencyCode])) {
            return null;
        }

        if ((float) $usdRates[$currencyCode] <= 0) {
            return null;
        }

        return (float) $usdRates[$baseCurrencyCode] / (float) $usdRates[$currencyCode];
    }
}
