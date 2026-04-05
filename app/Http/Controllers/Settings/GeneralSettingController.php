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
}
