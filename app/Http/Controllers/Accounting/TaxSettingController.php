<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('accounting/TaxSetting', [
            'taxRate' => (float) AppSetting::get('tax_rate', 0),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        AppSetting::set('tax_rate', (string) $validated['tax_rate']);

        return to_route('accounting.tax-setting.edit')->with('status', 'tax-rate-updated');
    }
}
