<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function index(): Response
    {
        $baseCurrencyCode = (string) AppSetting::get('default_currency_code', 'IDR');

        $rates = ExchangeRate::query()
            ->with('creator:id,name')
            ->orderByDesc('rate_date')
            ->orderBy('currency_code')
            ->get()
            ->map(fn (ExchangeRate $rate): array => [
                'id' => $rate->id,
                'currency_code' => $rate->currency_code,
                'rate_to_base' => (string) $rate->rate_to_base,
                'rate_date' => $rate->rate_date->format('Y-m-d'),
                'created_by' => $rate->creator?->name,
            ])
            ->values();

        return Inertia::render('accounting/ExchangeRates', [
            'baseCurrencyCode' => $baseCurrencyCode,
            'rates' => $rates,
            'currencies' => Currency::query()
                ->where('code', '!=', $baseCurrencyCode)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $baseCurrencyCode = (string) AppSetting::get('default_currency_code', 'IDR');

        $validated = $request->validate([
            'currency_code' => [
                'required',
                'string',
                'size:3',
                'exists:currencies,code',
                Rule::notIn([$baseCurrencyCode]),
            ],
            'rate_to_base' => ['required', 'numeric', 'min:0.000001'],
            'rate_date' => ['required', 'date'],
        ], [
            'currency_code.not_in' => 'Tidak perlu exchange rate untuk base currency itu sendiri.',
        ]);

        ExchangeRate::query()->updateOrCreate(
            [
                'currency_code' => strtoupper($validated['currency_code']),
                'rate_date' => $validated['rate_date'],
            ],
            [
                'rate_to_base' => $validated['rate_to_base'],
                'created_by' => auth()->id(),
            ],
        );

        return back()->with('success', 'Exchange rate berhasil disimpan.');
    }

    public function destroy(ExchangeRate $exchangeRate): RedirectResponse
    {
        $exchangeRate->delete();

        return back()->with('success', 'Exchange rate berhasil dihapus.');
    }

    /**
     * Pull today's rates from the exchange rate API (open.er-api.com, USD-anchored)
     * and upsert a rate for every active non-base currency, dated today.
     */
    public function fetchLatest(ExchangeRateApiService $apiService): RedirectResponse
    {
        $baseCurrencyCode = strtoupper((string) AppSetting::get('default_currency_code', 'IDR'));

        $usdRates = $apiService->fetchUsdRates();

        if ($usdRates === null) {
            return back()->with('error', 'Gagal mengambil data kurs dari API (open.er-api.com). Coba lagi nanti.');
        }

        if (! isset($usdRates[$baseCurrencyCode])) {
            return back()->with('error', "Base currency {$baseCurrencyCode} tidak dikenali oleh API kurs. Gunakan kode ISO 4217 standar (mis. IDR, USD, EUR) atau input rate secara manual.");
        }

        $currencyCodes = Currency::query()
            ->where('code', '!=', $baseCurrencyCode)
            ->where('is_active', true)
            ->pluck('code');

        $today = now()->format('Y-m-d');
        $updated = 0;
        $skipped = [];

        foreach ($currencyCodes as $code) {
            $rate = $apiService->rateToBase($usdRates, $code, $baseCurrencyCode);

            if ($rate === null) {
                $skipped[] = $code;

                continue;
            }

            ExchangeRate::query()->updateOrCreate(
                ['currency_code' => strtoupper($code), 'rate_date' => $today],
                ['rate_to_base' => $rate, 'created_by' => auth()->id()],
            );
            $updated++;
        }

        $message = "{$updated} kurs berhasil diperbarui dari API.";

        if ($skipped !== []) {
            $message .= ' Tidak dikenali oleh API: '.implode(', ', $skipped).' (input manual untuk currency ini).';
        }

        return back()->with('success', $message);
    }
}
