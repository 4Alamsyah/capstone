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

    public const PERMISSION_MENU_DASHBOARD = 'menu.dashboard';

    public const PERMISSION_MENU_PARTS = 'menu.parts';

    public const PERMISSION_MENU_SUPPLIERS = 'menu.suppliers';

    public const PERMISSION_MENU_WORK_ORDERS = 'menu.work_orders';

    public const PERMISSION_MENU_SALES = 'menu.sales';

    public const PERMISSION_MENU_PURCHASE = 'menu.purchase';

    public const PERMISSION_MENU_ACCOUNTING = 'menu.accounting';

    public const PERMISSION_MENU_GENERAL_SETTINGS = 'menu.settings.general';

    public const PERMISSION_MENU_ROLE_ACCESS = 'menu.settings.role_access';

    public const PERMISSION_APPROVE_PURCHASE_ORDER = 'approve.purchase_order';

    public const PERMISSION_APPROVE_INVOICE_PAYMENT = 'approve.invoice_payment';

    public const PERMISSION_APPROVE_PURCHASE_VOUCHER = 'approve.purchase_voucher';

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
     * Default permission matrix used as fallback.
     *
     * @return array<string, bool>
     */
    public static function defaultPermissions(): array
    {
        return [
            self::PERMISSION_MENU_DASHBOARD => true,
            self::PERMISSION_MENU_PARTS => true,
            self::PERMISSION_MENU_SUPPLIERS => true,
            self::PERMISSION_MENU_WORK_ORDERS => true,
            self::PERMISSION_MENU_SALES => true,
            self::PERMISSION_MENU_PURCHASE => true,
            self::PERMISSION_MENU_ACCOUNTING => true,
            self::PERMISSION_MENU_GENERAL_SETTINGS => true,
            self::PERMISSION_MENU_ROLE_ACCESS => false,
            self::PERMISSION_APPROVE_PURCHASE_ORDER => false,
            self::PERMISSION_APPROVE_INVOICE_PAYMENT => false,
            self::PERMISSION_APPROVE_PURCHASE_VOUCHER => false,
        ];
    }

    /**
     * Default permission template by role.
     *
     * @return array<string, bool>
     */
    public static function permissionsTemplateForRole(string $role): array
    {
        if ($role === self::ROLE_ADMIN) {
            return array_fill_keys(array_keys(self::defaultPermissions()), true);
        }

        $template = self::defaultPermissions();

        if (in_array($role, [self::ROLE_GM, self::ROLE_DIRECTOR], true)) {
            $template[self::PERMISSION_MENU_ROLE_ACCESS] = true;
            $template[self::PERMISSION_APPROVE_PURCHASE_ORDER] = true;
            $template[self::PERMISSION_APPROVE_INVOICE_PAYMENT] = true;
            $template[self::PERMISSION_APPROVE_PURCHASE_VOUCHER] = true;
        }

        return $template;
    }

    /**
     * Human-friendly labels for permission setting page.
     *
     * @return array<string, string>
     */
    public static function permissionLabels(): array
    {
        return [
            self::PERMISSION_MENU_DASHBOARD => 'View Dashboard menu',
            self::PERMISSION_MENU_PARTS => 'View Part menu',
            self::PERMISSION_MENU_SUPPLIERS => 'View Supplier menu',
            self::PERMISSION_MENU_WORK_ORDERS => 'View Manufacture Order menu',
            self::PERMISSION_MENU_SALES => 'View Sales menu',
            self::PERMISSION_MENU_PURCHASE => 'View Purchase menu',
            self::PERMISSION_MENU_ACCOUNTING => 'View Accounting menu',
            self::PERMISSION_MENU_GENERAL_SETTINGS => 'View General Setting menu',
            self::PERMISSION_MENU_ROLE_ACCESS => 'View Role Access setting menu',
            self::PERMISSION_APPROVE_PURCHASE_ORDER => 'Can approve/reject Purchase Order',
            self::PERMISSION_APPROVE_INVOICE_PAYMENT => 'Can approve/reject invoice payment',
            self::PERMISSION_APPROVE_PURCHASE_VOUCHER => 'Can approve/reject Purchase Voucher',
        ];
    }

    /**
     * Merge user-defined permissions with defaults.
     *
     * @return array<string, bool>
     */
    public function resolvedPermissions(): array
    {
        if ($this->role === self::ROLE_ADMIN) {
            return array_fill_keys(array_keys(self::defaultPermissions()), true);
        }

        $defaults = self::permissionsTemplateForRole((string) $this->role);

        $raw = is_array($this->permissions) ? $this->permissions : [];

        foreach ($defaults as $key => $defaultValue) {
            $defaults[$key] = (bool) ($raw[$key] ?? $defaultValue);
        }

        return $defaults;
    }

    /**
     * Check whether user has specific permission key.
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
}
