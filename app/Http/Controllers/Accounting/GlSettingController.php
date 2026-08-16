<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('accounting/GlSetting', [
            'mapping' => [
                'gl_ar_account_id' => $this->intOrNull(AppSetting::get('gl_ar_account_id')),
                'gl_sales_revenue_account_id' => $this->intOrNull(AppSetting::get('gl_sales_revenue_account_id')),
                'gl_sales_tax_payable_account_id' => $this->intOrNull(AppSetting::get('gl_sales_tax_payable_account_id')),
                'gl_cash_bank_account_id' => $this->intOrNull(AppSetting::get('gl_cash_bank_account_id')),
                'gl_ap_account_id' => $this->intOrNull(AppSetting::get('gl_ap_account_id')),
                'gl_purchase_expense_account_id' => $this->intOrNull(AppSetting::get('gl_purchase_expense_account_id')),
                'gl_purchase_tax_input_account_id' => $this->intOrNull(AppSetting::get('gl_purchase_tax_input_account_id')),
                'gl_realized_fx_gain_account_id' => $this->intOrNull(AppSetting::get('gl_realized_fx_gain_account_id')),
                'gl_realized_fx_loss_account_id' => $this->intOrNull(AppSetting::get('gl_realized_fx_loss_account_id')),
                'gl_unrealized_fx_gain_account_id' => $this->intOrNull(AppSetting::get('gl_unrealized_fx_gain_account_id')),
                'gl_unrealized_fx_loss_account_id' => $this->intOrNull(AppSetting::get('gl_unrealized_fx_loss_account_id')),
            ],
            'accounts' => ChartOfAccount::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (ChartOfAccount $account): array => [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                ]),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gl_ar_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'gl_sales_revenue_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'gl_sales_tax_payable_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'gl_cash_bank_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'gl_ap_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'gl_purchase_expense_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'gl_purchase_tax_input_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'gl_realized_fx_gain_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'gl_realized_fx_loss_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'gl_unrealized_fx_gain_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'gl_unrealized_fx_loss_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
        ]);

        foreach ($validated as $key => $value) {
            if ($value === null) {
                continue;
            }

            AppSetting::set($key, (string) $value);
        }

        return to_route('accounting.gl-setting.edit')->with('status', 'gl-mapping-updated');
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value !== null ? (int) $value : null;
    }
}
