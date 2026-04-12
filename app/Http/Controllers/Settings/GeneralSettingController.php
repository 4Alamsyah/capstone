<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingController extends Controller
{
    public function edit(): Response
    {
        $defaultCode = (string) AppSetting::get('default_currency_code', 'IDR');

        $currencies = Currency::query()
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol', 'is_active', 'is_default']);

        if ($currencies->isEmpty()) {
            $currency = Currency::query()->create([
                'code' => 'IDR',
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'is_active' => true,
                'is_default' => true,
            ]);

            AppSetting::set('default_currency_code', $currency->code);
            $defaultCode = $currency->code;

            $currencies = Currency::query()
                ->orderByDesc('is_default')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'symbol', 'is_active', 'is_default']);
        }

        if (! $currencies->contains(fn (Currency $currency): bool => $currency->code === $defaultCode)) {
            $fallback = $currencies->first();

            if ($fallback instanceof Currency) {
                $defaultCode = $fallback->code;
                AppSetting::set('default_currency_code', $defaultCode);
            }
        }

        Currency::query()->update([
            'is_default' => false,
        ]);

        Currency::query()->where('code', $defaultCode)->update([
            'is_default' => true,
        ]);

        return Inertia::render('settings/GeneralSettings', [
            'settings' => [
                'wo_prefix' => AppSetting::get('wo_prefix', 'WO'),
                'default_currency_code' => $defaultCode,
            ],
            'currencies' => Currency::query()
                ->orderByDesc('is_default')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'symbol', 'is_active', 'is_default'])
                ->map(fn (Currency $currency): array => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'is_active' => $currency->is_active,
                    'is_default' => $currency->is_default,
                ]),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wo_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-_]+$/i'],
            'default_currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
        ]);

        $woPrefix = strtoupper(trim($validated['wo_prefix']));
        $defaultCode = strtoupper(trim($validated['default_currency_code']));

        AppSetting::set('wo_prefix', $woPrefix);
        AppSetting::set('default_currency_code', $defaultCode);

        Currency::query()->update([
            'is_default' => false,
        ]);

        Currency::query()->where('code', $defaultCode)->update([
            'is_default' => true,
        ]);

        return to_route('general-settings.edit')->with('status', 'settings-updated');
    }

    public function storeCurrency(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'set_as_default' => ['nullable', 'boolean'],
        ]);

        $currency = Currency::query()->create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'symbol' => isset($validated['symbol']) ? trim((string) $validated['symbol']) : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_default' => false,
        ]);

        $hasDefault = Currency::query()->where('is_default', true)->exists();
        $shouldBeDefault = (bool) ($validated['set_as_default'] ?? false) || ! $hasDefault;

        if ($shouldBeDefault) {
            Currency::query()->update(['is_default' => false]);
            $currency->update(['is_default' => true]);
            AppSetting::set('default_currency_code', $currency->code);
        }

        return to_route('general-settings.edit')->with('status', 'currency-added');
    }

    public function setDefaultCurrency(Currency $currency): RedirectResponse
    {
        Currency::query()->update(['is_default' => false]);
        $currency->update(['is_default' => true]);

        AppSetting::set('default_currency_code', $currency->code);

        return to_route('general-settings.edit')->with('status', 'default-currency-updated');
    }

    public function paymentTerms(): Response
    {
        return Inertia::render('settings/PaymentTerms', [
            'paymentTerms' => $this->paymentTermList(),
            'status' => session('status'),
        ]);
    }

    public function storePaymentTerm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'term' => ['required', 'string', 'max:100'],
        ]);

        $term = $this->normalizePaymentTerm($validated['term']);
        $terms = $this->paymentTermList();

        if ($term !== '' && ! $this->containsPaymentTerm($terms, $term)) {
            $terms[] = $term;
        }

        $this->savePaymentTerms($terms);

        return to_route('general-settings.payment-terms.edit')->with('status', 'payment-term-added');
    }

    public function updatePaymentTerm(Request $request, int $index): RedirectResponse
    {
        $validated = $request->validate([
            'term' => ['required', 'string', 'max:100'],
        ]);

        $terms = $this->paymentTermList();

        if (! isset($terms[$index])) {
            return to_route('general-settings.payment-terms.edit')->with('status', 'payment-term-not-found');
        }

        $term = $this->normalizePaymentTerm($validated['term']);

        foreach ($terms as $key => $existing) {
            if ($key !== $index && mb_strtolower($existing) === mb_strtolower($term)) {
                return to_route('general-settings.payment-terms.edit')->with('status', 'payment-term-duplicate');
            }
        }

        $terms[$index] = $term;
        $this->savePaymentTerms($terms);

        return to_route('general-settings.payment-terms.edit')->with('status', 'payment-term-updated');
    }

    public function destroyPaymentTerm(int $index): RedirectResponse
    {
        $terms = $this->paymentTermList();

        if (! isset($terms[$index])) {
            return to_route('general-settings.payment-terms.edit')->with('status', 'payment-term-not-found');
        }

        unset($terms[$index]);
        $this->savePaymentTerms(array_values($terms));

        return to_route('general-settings.payment-terms.edit')->with('status', 'payment-term-removed');
    }

    /**
     * @return array<int, string>
     */
    private function paymentTermList(): array
    {
        $raw = AppSetting::get('payment_terms_options', '[]');

        if (! is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $terms = collect($decoded)
            ->filter(fn ($term): bool => is_string($term))
            ->map(fn (string $term): string => $this->normalizePaymentTerm($term))
            ->filter(fn (string $term): bool => $term !== '')
            ->unique(fn (string $term): string => mb_strtolower($term))
            ->values()
            ->all();

        return $terms;
    }

    /**
     * @param array<int, string> $terms
     */
    private function savePaymentTerms(array $terms): void
    {
        AppSetting::set('payment_terms_options', json_encode(array_values($terms), JSON_UNESCAPED_UNICODE));
    }

    private function normalizePaymentTerm(string $term): string
    {
        return trim(preg_replace('/\s+/', ' ', $term) ?? '');
    }

    /**
     * @param array<int, string> $terms
     */
    private function containsPaymentTerm(array $terms, string $term): bool
    {
        foreach ($terms as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($term)) {
                return true;
            }
        }

        return false;
    }
}
