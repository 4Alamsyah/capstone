<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts legacy boolean `menu.*` permission keys to the new leveled
     * `module.*` sub-module keys, preserving each user's current effective
     * access exactly (true -> full, false -> prohibited) so nobody loses
     * access on deploy. Admins can subsequently tighten per sub-module via
     * the Role Access UI. `approve.*` keys are untouched (still boolean).
     */
    public function up(): void
    {
        $map = [
            'menu.dashboard' => ['module.dashboard'],
            'menu.parts' => ['module.parts.master', 'module.parts.warehouse', 'module.parts.stock', 'module.parts.uom'],
            'menu.suppliers' => ['module.purchase.suppliers'],
            'menu.work_orders' => ['module.manufacturing.work_orders', 'module.manufacturing.work_centers'],
            'menu.sales' => ['module.sales.customers', 'module.sales.customer_orders', 'module.sales.quotations', 'module.sales.invoices'],
            'menu.purchase' => ['module.purchase.orders', 'module.purchase.vouchers', 'module.purchase.ap_invoices'],
            'menu.accounting' => [
                'module.accounting.chart_of_accounts',
                'module.accounting.fiscal_periods',
                'module.accounting.journal',
                'module.accounting.tax_gl_settings',
                'module.accounting.exchange_rates',
                'module.accounting.reports',
            ],
            'menu.settings.general' => ['module.settings.general'],
            'menu.settings.role_access' => ['module.settings.role_access'],
        ];

        $approveKeys = [
            'approve.purchase_order',
            'approve.invoice_payment',
            'approve.purchase_voucher',
            'approve.ap_invoice',
        ];

        DB::table('users')->select('id', 'permissions')->orderBy('id')->chunkById(100, function ($users) use ($map, $approveKeys): void {
            foreach ($users as $user) {
                $legacy = json_decode((string) $user->permissions, true);

                if (! is_array($legacy)) {
                    continue;
                }

                $updated = [];

                foreach ($map as $oldKey => $newKeys) {
                    if (! array_key_exists($oldKey, $legacy)) {
                        continue;
                    }

                    $level = $legacy[$oldKey] ? 'full' : 'prohibited';

                    foreach ($newKeys as $newKey) {
                        $updated[$newKey] = $level;
                    }
                }

                foreach ($approveKeys as $approveKey) {
                    if (array_key_exists($approveKey, $legacy)) {
                        $updated[$approveKey] = (bool) $legacy[$approveKey];
                    }
                }

                if ($updated === []) {
                    continue;
                }

                DB::table('users')->where('id', $user->id)->update([
                    'permissions' => json_encode($updated),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data migration: legacy boolean menu keys cannot be
        // reconstructed from the new leveled sub-module permissions.
    }
};
