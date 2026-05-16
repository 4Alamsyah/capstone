<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingController extends Controller
{
    public function edit(): Response
    {
        $woFormat = json_decode(AppSetting::get('wo_format', json_encode($this->defaultWoFormat())), true) ?? $this->defaultWoFormat();
        $poFormat = json_decode(AppSetting::get('po_format', json_encode($this->defaultPoFormat())), true) ?? $this->defaultPoFormat();
        $coFormat = json_decode(AppSetting::get('co_format', json_encode($this->defaultCoFormat())), true) ?? $this->defaultCoFormat();
        $quotationFormat = json_decode(AppSetting::get('quotation_format', json_encode($this->defaultQuotationFormat())), true) ?? $this->defaultQuotationFormat();

        return Inertia::render('settings/AppSettings', [
            'settings' => [
                'wo_format' => $woFormat,
                'po_format' => $poFormat,
                'co_format' => $coFormat,
                'quotation_format' => $quotationFormat,
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wo_format' => ['required', 'array'],
            'wo_format.prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-_]+$/i'],
            'wo_format.components' => ['required', 'array', 'min:1'],
            'wo_format.components.*.type' => ['required', 'string', 'in:prefix,year,month,sequential'],
            'wo_format.components.*.format' => ['required', 'string'],
            'wo_format.separator' => ['required', 'string', 'max:5'],

            'po_format' => ['required', 'array'],
            'po_format.prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-_]+$/i'],
            'po_format.components' => ['required', 'array', 'min:1'],
            'po_format.components.*.type' => ['required', 'string', 'in:prefix,year,month,sequential'],
            'po_format.components.*.format' => ['required', 'string'],
            'po_format.separator' => ['required', 'string', 'max:5'],

            'co_format' => ['required', 'array'],
            'co_format.prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-_]+$/i'],
            'co_format.components' => ['required', 'array', 'min:1'],
            'co_format.components.*.type' => ['required', 'string', 'in:prefix,year,month,sequential'],
            'co_format.components.*.format' => ['required', 'string'],
            'co_format.separator' => ['required', 'string', 'max:5'],

            'quotation_format' => ['required', 'array'],
            'quotation_format.prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-_]+$/i'],
            'quotation_format.components' => ['required', 'array', 'min:1'],
            'quotation_format.components.*.type' => ['required', 'string', 'in:prefix,year,month,sequential'],
            'quotation_format.components.*.format' => ['required', 'string'],
            'quotation_format.separator' => ['required', 'string', 'max:5'],
        ]);

        AppSetting::set('wo_format', json_encode($validated['wo_format']));
        AppSetting::set('po_format', json_encode($validated['po_format']));
        AppSetting::set('co_format', json_encode($validated['co_format']));
        AppSetting::set('quotation_format', json_encode($validated['quotation_format']));

        return to_route('app-settings.edit')->with('status', 'settings-updated');
    }

    private function defaultWoFormat(): array
    {
        return [
            'prefix' => 'WO',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ];
    }

    private function defaultPoFormat(): array
    {
        return [
            'prefix' => 'PO',
            'separator' => '-',
            'components' => [
                ['type' => 'prefix', 'format' => 'raw'],
                ['type' => 'year', 'format' => 'YYYY'],
                ['type' => 'month', 'format' => 'MM'],
                ['type' => 'sequential', 'format' => '5'],
            ],
        ];
    }

    private function defaultCoFormat(): array
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

    private function defaultQuotationFormat(): array
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
}
