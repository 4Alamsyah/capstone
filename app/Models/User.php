<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const ROLE_STAFF = 'staff';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_GM = 'gm';

    public const ROLE_DIRECTOR = 'director';

    public const LEVEL_PROHIBITED = 'prohibited';

    public const LEVEL_VIEW = 'view';

    public const LEVEL_EDIT = 'edit';

    public const LEVEL_FULL = 'full';

    public const LEVEL_RANK = [
        self::LEVEL_PROHIBITED => 0,
        self::LEVEL_VIEW => 1,
        self::LEVEL_EDIT => 2,
        self::LEVEL_FULL => 3,
    ];

    public const PERMISSION_MODULE_DASHBOARD = 'module.dashboard';

    public const PERMISSION_MODULE_PARTS_MASTER = 'module.parts.master';

    public const PERMISSION_MODULE_PARTS_WAREHOUSE = 'module.parts.warehouse';

    public const PERMISSION_MODULE_PARTS_STOCK = 'module.parts.stock';

    public const PERMISSION_MODULE_PARTS_UOM = 'module.parts.uom';

    public const PERMISSION_MODULE_MANUFACTURING_WORK_ORDERS = 'module.manufacturing.work_orders';

    public const PERMISSION_MODULE_MANUFACTURING_WORK_CENTERS = 'module.manufacturing.work_centers';

    public const PERMISSION_MODULE_SALES_CUSTOMERS = 'module.sales.customers';

    public const PERMISSION_MODULE_SALES_CUSTOMER_ORDERS = 'module.sales.customer_orders';

    public const PERMISSION_MODULE_SALES_QUOTATIONS = 'module.sales.quotations';

    public const PERMISSION_MODULE_SALES_INVOICES = 'module.sales.invoices';

    public const PERMISSION_MODULE_PURCHASE_SUPPLIERS = 'module.purchase.suppliers';

    public const PERMISSION_MODULE_PURCHASE_ORDERS = 'module.purchase.orders';

    public const PERMISSION_MODULE_PURCHASE_VOUCHERS = 'module.purchase.vouchers';

    public const PERMISSION_MODULE_PURCHASE_AP_INVOICES = 'module.purchase.ap_invoices';

    public const PERMISSION_MODULE_ACCOUNTING_CHART_OF_ACCOUNTS = 'module.accounting.chart_of_accounts';

    public const PERMISSION_MODULE_ACCOUNTING_FISCAL_PERIODS = 'module.accounting.fiscal_periods';

    public const PERMISSION_MODULE_ACCOUNTING_JOURNAL = 'module.accounting.journal';

    public const PERMISSION_MODULE_ACCOUNTING_TAX_GL_SETTINGS = 'module.accounting.tax_gl_settings';

    public const PERMISSION_MODULE_ACCOUNTING_EXCHANGE_RATES = 'module.accounting.exchange_rates';

    public const PERMISSION_MODULE_ACCOUNTING_REPORTS = 'module.accounting.reports';

    public const PERMISSION_MODULE_SETTINGS_GENERAL = 'module.settings.general';

    public const PERMISSION_MODULE_SETTINGS_ROLE_ACCESS = 'module.settings.role_access';

    public const PERMISSION_MODULE_SETTINGS_APP = 'module.settings.app';

    public const PERMISSION_APPROVE_PURCHASE_ORDER = 'approve.purchase_order';

    public const PERMISSION_APPROVE_INVOICE_PAYMENT = 'approve.invoice_payment';

    public const PERMISSION_APPROVE_PURCHASE_VOUCHER = 'approve.purchase_voucher';

    public const PERMISSION_APPROVE_AP_INVOICE = 'approve.ap_invoice';

    /**
     * Module/sub-module tree used to build the role access permission matrix.
     *
     * @var array<string, array{label: string, submodules: array<string, string>}>
     */
    public const MODULES = [
        'dashboard' => [
            'label' => 'Dashboard',
            'submodules' => [
                self::PERMISSION_MODULE_DASHBOARD => 'Dashboard',
            ],
        ],
        'parts' => [
            'label' => 'Parts',
            'submodules' => [
                self::PERMISSION_MODULE_PARTS_MASTER => 'Part Master (Part & BOM)',
                self::PERMISSION_MODULE_PARTS_WAREHOUSE => 'Warehouse',
                self::PERMISSION_MODULE_PARTS_STOCK => 'Stock & Tool Loan',
                self::PERMISSION_MODULE_PARTS_UOM => 'UOM',
            ],
        ],
        'manufacturing' => [
            'label' => 'Manufacturing',
            'submodules' => [
                self::PERMISSION_MODULE_MANUFACTURING_WORK_ORDERS => 'Work Order',
                self::PERMISSION_MODULE_MANUFACTURING_WORK_CENTERS => 'Work Center',
            ],
        ],
        'sales' => [
            'label' => 'Sales',
            'submodules' => [
                self::PERMISSION_MODULE_SALES_CUSTOMERS => 'Customer',
                self::PERMISSION_MODULE_SALES_CUSTOMER_ORDERS => 'Customer Order',
                self::PERMISSION_MODULE_SALES_QUOTATIONS => 'Quotation',
                self::PERMISSION_MODULE_SALES_INVOICES => 'Invoice',
            ],
        ],
        'purchase' => [
            'label' => 'Purchase',
            'submodules' => [
                self::PERMISSION_MODULE_PURCHASE_SUPPLIERS => 'Supplier',
                self::PERMISSION_MODULE_PURCHASE_ORDERS => 'Purchase Order',
                self::PERMISSION_MODULE_PURCHASE_VOUCHERS => 'Purchase Voucher',
                self::PERMISSION_MODULE_PURCHASE_AP_INVOICES => 'AP Invoice',
            ],
        ],
        'accounting' => [
            'label' => 'Accounting',
            'submodules' => [
                self::PERMISSION_MODULE_ACCOUNTING_CHART_OF_ACCOUNTS => 'Chart of Accounts',
                self::PERMISSION_MODULE_ACCOUNTING_FISCAL_PERIODS => 'Fiscal Period',
                self::PERMISSION_MODULE_ACCOUNTING_JOURNAL => 'Journal',
                self::PERMISSION_MODULE_ACCOUNTING_TAX_GL_SETTINGS => 'Tax & GL Setting',
                self::PERMISSION_MODULE_ACCOUNTING_EXCHANGE_RATES => 'Exchange Rate & FX Revaluation',
                self::PERMISSION_MODULE_ACCOUNTING_REPORTS => 'Reports',
            ],
        ],
        'settings' => [
            'label' => 'Settings',
            'submodules' => [
                self::PERMISSION_MODULE_SETTINGS_GENERAL => 'General Setting',
                self::PERMISSION_MODULE_SETTINGS_ROLE_ACCESS => 'Role Access',
                self::PERMISSION_MODULE_SETTINGS_APP => 'App Setting',
            ],
        ],
    ];

    /**
     * The model's default attribute values.
     *
     * Ensures a freshly-instantiated user is active even before an explicit
     * value is set or the DB column default is round-tripped back.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'permissions',
        'is_active',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check whether user can approve management-level transactions.
     */
    public function isManagement(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_GM, self::ROLE_DIRECTOR], true);
    }

    /**
     * Flat list of all sub-module permission keys (in module/MODULES order).
     *
     * @return list<string>
     */
    public static function submodulePermissionKeys(): array
    {
        $keys = [];

        foreach (self::MODULES as $module) {
            $keys = [...$keys, ...array_keys($module['submodules'])];
        }

        return $keys;
    }

    /**
     * Flat list of the boolean approval permission keys.
     *
     * @return list<string>
     */
    public static function approvePermissionKeys(): array
    {
        return [
            self::PERMISSION_APPROVE_PURCHASE_ORDER,
            self::PERMISSION_APPROVE_INVOICE_PAYMENT,
            self::PERMISSION_APPROVE_PURCHASE_VOUCHER,
            self::PERMISSION_APPROVE_AP_INVOICE,
        ];
    }

    /**
     * Human-friendly labels for the approval permission checkboxes.
     *
     * @return array<string, string>
     */
    public static function approvePermissionLabels(): array
    {
        return [
            self::PERMISSION_APPROVE_PURCHASE_ORDER => 'Can approve/reject Purchase Order',
            self::PERMISSION_APPROVE_INVOICE_PAYMENT => 'Can approve/reject invoice payment',
            self::PERMISSION_APPROVE_PURCHASE_VOUCHER => 'Can approve/reject Purchase Voucher',
            self::PERMISSION_APPROVE_AP_INVOICE => 'Can approve/reject AP Invoice',
        ];
    }

    /**
     * Human-friendly labels for the 4 access levels, in ranked order.
     *
     * @return array<string, string>
     */
    public static function permissionLevels(): array
    {
        return [
            self::LEVEL_PROHIBITED => 'Prohibited',
            self::LEVEL_VIEW => 'Read Only',
            self::LEVEL_EDIT => 'Read & Edit',
            self::LEVEL_FULL => 'Read, Edit, Delete & Create',
        ];
    }

    /**
     * Default sub-module access level for a given role.
     */
    private static function defaultLevelForRole(string $role, string $key): string
    {
        if ($role === self::ROLE_ADMIN || in_array($role, [self::ROLE_GM, self::ROLE_DIRECTOR], true)) {
            return self::LEVEL_FULL;
        }

        // Staff defaults: full operational access, read-only accounting, no settings access.
        if ($key === self::PERMISSION_MODULE_DASHBOARD) {
            return self::LEVEL_VIEW;
        }

        if (str_starts_with($key, 'module.settings.')) {
            return $key === self::PERMISSION_MODULE_SETTINGS_GENERAL ? self::LEVEL_VIEW : self::LEVEL_PROHIBITED;
        }

        if (str_starts_with($key, 'module.accounting.')) {
            return self::LEVEL_VIEW;
        }

        return self::LEVEL_FULL;
    }

    /**
     * Default permission template (sub-module levels + approval flags) for a role.
     *
     * @return array<string, string|bool>
     */
    public static function permissionsTemplateForRole(string $role): array
    {
        $template = [];

        foreach (self::submodulePermissionKeys() as $key) {
            $template[$key] = self::defaultLevelForRole($role, $key);
        }

        $canApproveByDefault = $role === self::ROLE_ADMIN || in_array($role, [self::ROLE_GM, self::ROLE_DIRECTOR], true);

        foreach (self::approvePermissionKeys() as $key) {
            $template[$key] = $canApproveByDefault;
        }

        return $template;
    }

    /**
     * Merge user-defined permissions with role defaults, validating stored values.
     *
     * @return array<string, string|bool>
     */
    public function resolvedPermissions(): array
    {
        $template = self::permissionsTemplateForRole((string) $this->role);

        if ($this->role === self::ROLE_ADMIN) {
            return $template;
        }

        $raw = is_array($this->permissions) ? $this->permissions : [];
        $resolved = $template;

        foreach (self::submodulePermissionKeys() as $key) {
            $value = $raw[$key] ?? null;
            $resolved[$key] = is_string($value) && array_key_exists($value, self::LEVEL_RANK)
                ? $value
                : $template[$key];
        }

        foreach (self::approvePermissionKeys() as $key) {
            $resolved[$key] = array_key_exists($key, $raw) ? (bool) $raw[$key] : $template[$key];
        }

        return $resolved;
    }

    /**
     * Resolved access level for a sub-module permission key.
     */
    public function permissionLevel(string $key): string
    {
        $value = $this->resolvedPermissions()[$key] ?? self::LEVEL_PROHIBITED;

        return is_string($value) ? $value : self::LEVEL_PROHIBITED;
    }

    /**
     * Check whether user's access level for a sub-module meets the minimum required level.
     */
    public function hasAccess(string $key, string $minLevel = self::LEVEL_VIEW): bool
    {
        $rank = self::LEVEL_RANK[$this->permissionLevel($key)] ?? 0;
        $minRank = self::LEVEL_RANK[$minLevel] ?? 0;

        return $rank >= $minRank;
    }

    /**
     * Check whether user has specific boolean permission key (e.g. approval flags).
     */
    public function hasPermission(string $key): bool
    {
        return (bool) ($this->resolvedPermissions()[$key] ?? false);
    }

    /**
     * Check whether user can approve/reject purchase order.
     */
    public function canApprovePurchaseOrder(): bool
    {
        return $this->isManagement() && $this->hasPermission(self::PERMISSION_APPROVE_PURCHASE_ORDER);
    }

    /**
     * Check whether user can approve/reject invoice payment.
     */
    public function canApproveInvoicePayment(): bool
    {
        return $this->isManagement() && $this->hasPermission(self::PERMISSION_APPROVE_INVOICE_PAYMENT);
    }

    /**
     * Check whether user can approve/reject purchase voucher.
     */
    public function canApprovePurchaseVoucher(): bool
    {
        return $this->isManagement() && $this->hasPermission(self::PERMISSION_APPROVE_PURCHASE_VOUCHER);
    }

    /**
     * Check whether user can approve/reject AP invoice.
     */
    public function canApproveApInvoice(): bool
    {
        return $this->isManagement() && $this->hasPermission(self::PERMISSION_APPROVE_AP_INVOICE);
    }
}
