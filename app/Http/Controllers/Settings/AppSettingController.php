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
        return Inertia::render('settings/AppSettings', [
            'settings' => [
                'wo_prefix' => AppSetting::get('wo_prefix', 'WO'),
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wo_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-_]+$/i'],
        ]);

        AppSetting::set('wo_prefix', strtoupper(trim($validated['wo_prefix'])));

        return to_route('app-settings.edit')->with('status', 'settings-updated');
    }
}
