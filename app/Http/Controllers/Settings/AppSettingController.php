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
        $woFormat = json_decode(AppSetting::get('wo_format', json_encode($this->defaultWoFormat())), true);

        return Inertia::render('settings/AppSettings', [
            'settings' => [
                'wo_format' => $woFormat,
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
        ]);

        AppSetting::set('wo_format', json_encode($validated['wo_format']));

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
}
