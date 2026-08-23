<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    BookOpen,
    Calculator,
    FolderGit2,
    LayoutGrid,
    Package,
    PackageSearch,
    Settings,
    ShoppingCart,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type PermissionValue = boolean | 'prohibited' | 'view' | 'edit' | 'full';

type AuthUser = {
    permissions?: Record<string, PermissionValue>;
};

type SharedPageProps = {
    auth?: {
        user?: AuthUser | null;
    };
};

const page = usePage<SharedPageProps>();
const { t } = useI18n();

const hasAccess = (key: string): boolean => {
    const value = page.props.auth?.user?.permissions?.[key];

    return typeof value === 'string' ? value !== 'prohibited' : Boolean(value);
};

/**
 * Top-level items are filtered by permission using `href` (stable across
 * locales) rather than `title` (which changes when the language switches).
 */
const dashboardHref = dashboard();

/**
 * Maps each leaf nav item's `href` to the sub-module permission key that
 * gates it. Group nodes (items with children) are not listed here — their
 * visibility is derived from whether any of their descendants remain
 * visible after filtering.
 */
const HREF_PERMISSION_KEYS: Record<string, string> = {
    '/dashboard': 'module.dashboard',

    '/parts': 'module.parts.master',
    '/parts/register': 'module.parts.master',
    '/bom': 'module.parts.master',
    '/parts/warehouses': 'module.parts.warehouse',
    '/parts/stock': 'module.parts.stock',
    '/parts/uoms': 'module.parts.uom',

    '/work-orders': 'module.manufacturing.work_orders',
    '/work-orders/report': 'module.manufacturing.work_orders',
    '/work-orders/logs': 'module.manufacturing.work_orders',
    '/work-orders/lead-time': 'module.manufacturing.work_orders',
    '/work-centers': 'module.manufacturing.work_centers',

    '/sales/customer-orders': 'module.sales.customer_orders',
    '/sales/customer-orders/create': 'module.sales.customer_orders',
    '/sales/quotations': 'module.sales.quotations',
    '/sales/quotations/create': 'module.sales.quotations',
    '/sales/invoices': 'module.sales.invoices',
    '/sales/invoices/create': 'module.sales.invoices',
    '/sales/customers': 'module.sales.customers',

    '/purchase/po': 'module.purchase.orders',
    '/purchase/po/create': 'module.purchase.orders',
    '/purchase/po/arrivals': 'module.purchase.orders',
    '/purchase/po/arrivals/logs': 'module.purchase.orders',
    '/purchase/voucher': 'module.purchase.vouchers',
    '/purchase/voucher/create': 'module.purchase.vouchers',
    '/purchase/voucher/stock-recommendations': 'module.purchase.vouchers',
    '/purchase/ap/invoices': 'module.purchase.ap_invoices',
    '/suppliers': 'module.purchase.suppliers',

    '/accounting/general': 'module.accounting.chart_of_accounts',
    '/accounting/chart-of-accounts': 'module.accounting.chart_of_accounts',
    '/accounting/fiscal-periods': 'module.accounting.fiscal_periods',
    '/accounting/manual-journal': 'module.accounting.journal',
    '/accounting/journal-entries': 'module.accounting.journal',
    '/accounting/journal-report': 'module.accounting.journal',
    '/accounting/journal-lines': 'module.accounting.journal',
    '/accounting/profit-loss': 'module.accounting.journal',
    '/accounting/tax-setting': 'module.accounting.tax_gl_settings',
    '/accounting/gl-setting': 'module.accounting.tax_gl_settings',
    '/accounting/exchange-rates': 'module.accounting.exchange_rates',
    '/accounting/fx-revaluation': 'module.accounting.exchange_rates',
    '/accounting/balance-sheet': 'module.accounting.reports',
    '/accounting/ar-aging': 'module.accounting.reports',
    '/accounting/ap-aging': 'module.accounting.reports',
    '/accounting/audit-trails': 'module.accounting.reports',

    '/settings/general': 'module.settings.general',
    '/settings/general/payment-terms': 'module.settings.general',
    '/settings/role-access': 'module.settings.role_access',
};

/**
 * Nav item `href` values are either plain path strings or Wayfinder
 * `{ url, method }` route definitions (e.g. `dashboard()`); normalize both
 * to the path string used as the HREF_PERMISSION_KEYS lookup key.
 */
const resolveHrefPath = (href: NavItem['href']): string | undefined => {
    if (typeof href === 'string') {
        return href;
    }

    if (href && typeof href === 'object' && 'url' in href && typeof href.url === 'string') {
        return href.url;
    }

    return undefined;
};

/**
 * Recursively filters the nav tree: a leaf is kept if the user has access to
 * its mapped permission key (or it has none), a group is kept if at least
 * one of its descendants survives filtering.
 */
const filterNavByPermission = (items: NavItem[]): NavItem[] => {
    return items.reduce<NavItem[]>((acc, item) => {
        if (item.items && item.items.length > 0) {
            const children = filterNavByPermission(item.items);

            if (children.length > 0) {
                acc.push({ ...item, items: children });
            }

            return acc;
        }

        const path = resolveHrefPath(item.href);
        const key = path ? HREF_PERMISSION_KEYS[path] : undefined;

        if (!key || hasAccess(key)) {
            acc.push(item);
        }

        return acc;
    }, []);
};

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboardHref,
            icon: LayoutGrid,
            items: undefined,
        },
        {
            title: t('nav.part'),
            href: '/parts',
            icon: PackageSearch,
            items: [
                {
                    title: t('nav.part_master'),
                    href: '/parts',
                    items: [
                        {
                            title: t('nav.part_list'),
                            href: '/parts',
                        },
                        {
                            title: t('nav.register_part'),
                            href: '/parts/register',
                        },
                        {
                            title: t('nav.bom'),
                            href: '/bom',
                        },
                    ],
                },
                {
                    title: t('nav.inventory'),
                    href: '/parts/warehouses',
                    items: [
                        {
                            title: t('nav.warehouse'),
                            href: '/parts/warehouses',
                        },
                        {
                            title: t('nav.stock'),
                            href: '/parts/stock',
                        },
                        {
                            title: t('nav.uom'),
                            href: '/parts/uoms',
                        },
                    ],
                },
            ],
        },

        {
            title: t('nav.manufacturing'),
            href: '/work-orders',
            icon: LayoutGrid,
            items: [
                {
                    title: t('nav.work_order'),
                    href: '/work-orders',
                    items: [
                        {
                            title: t('nav.mo_list'),
                            href: '/work-orders',
                        },
                        {
                            title: t('nav.report_mo'),
                            href: '/work-orders/report',
                        },
                        {
                            title: t('nav.log_mo'),
                            href: '/work-orders/logs',
                        },
                        {
                            title: t('nav.lead_time'),
                            href: '/work-orders/lead-time',
                        },
                    ],
                },
                {
                    title: t('nav.work_center'),
                    href: '/work-centers',
                },
            ],
        },
        {
            title: t('nav.sales'),
            href: '/sales/customer-orders',
            icon: ShoppingCart,
            items: [
                {
                    title: t('nav.customer_order'),
                    href: '/sales/customer-orders',
                    items: [
                        {
                            title: t('nav.register_co'),
                            href: '/sales/customer-orders/create',
                        },
                        {
                            title: t('nav.order_list'),
                            href: '/sales/customer-orders',
                        },
                    ],
                },
                {
                    title: t('nav.quotation'),
                    href: '/sales/quotations',
                    items: [
                        {
                            title: t('nav.register_quotation'),
                            href: '/sales/quotations/create',
                        },
                        {
                            title: t('nav.quotation_list'),
                            href: '/sales/quotations',
                        },
                    ],
                },
                {
                    title: t('nav.invoice'),
                    href: '/sales/invoices',
                    items: [
                        {
                            title: t('nav.register_invoice'),
                            href: '/sales/invoices/create',
                        },
                        {
                            title: t('nav.invoice_list'),
                            href: '/sales/invoices',
                        },
                    ],
                },
                {
                    title: t('nav.customer_register'),
                    href: '/sales/customers',
                },
            ],
        },
        {
            title: t('nav.purchase'),
            href: '/purchase/po',
            icon: Package,
            items: [
                {
                    title: t('nav.po'),
                    href: '/purchase/po',
                    items: [
                        {
                            title: t('nav.register_po'),
                            href: '/purchase/po/create',
                        },
                        {
                            title: t('nav.list_po'),
                            href: '/purchase/po',
                        },
                        {
                            title: t('nav.report_arrival'),
                            href: '/purchase/po/arrivals',
                        },
                        {
                            title: t('nav.log_report'),
                            href: '/purchase/po/arrivals/logs',
                        },
                    ],
                },
                {
                    title: t('nav.voucher'),
                    href: '/purchase/voucher',
                    items: [
                        {
                            title: t('nav.register_voucher'),
                            href: '/purchase/voucher/create',
                        },
                        {
                            title: t('nav.list_voucher'),
                            href: '/purchase/voucher',
                        },
                        {
                            title: t('nav.stock_recommendation'),
                            href: '/purchase/voucher/stock-recommendations',
                        },
                    ],
                },
                {
                    title: t('nav.ap_invoice'),
                    href: '/purchase/ap/invoices',
                },
                {
                    title: t('nav.supplier'),
                    href: '/suppliers',
                },
            ],
        },
        {
            title: t('nav.accounting'),
            href: '/accounting/general',
            icon: Calculator,
            items: [
                {
                    title: t('nav.general'),
                    href: '/accounting/general',
                },
                {
                    title: t('nav.journal'),
                    href: '/accounting/journal-entries',
                    items: [
                        {
                            title: t('nav.manual_journal'),
                            href: '/accounting/manual-journal',
                        },
                        {
                            title: t('nav.journal_entries'),
                            href: '/accounting/journal-entries',
                        },
                        {
                            title: t('nav.journal_report'),
                            href: '/accounting/journal-report',
                        },
                        {
                            title: t('nav.journal_lines'),
                            href: '/accounting/journal-lines',
                        },
                        {
                            title: t('nav.profit_loss'),
                            href: '/accounting/profit-loss',
                        },
                        {
                            title: t('nav.fx_revaluation'),
                            href: '/accounting/fx-revaluation',
                        },
                    ],
                },
                {
                    title: t('nav.master_data'),
                    href: '/accounting/chart-of-accounts',
                    items: [
                        {
                            title: t('nav.chart_of_accounts'),
                            href: '/accounting/chart-of-accounts',
                        },
                        {
                            title: t('nav.fiscal_periods'),
                            href: '/accounting/fiscal-periods',
                        },
                    ],
                },
                {
                    title: t('nav.reports'),
                    href: '/accounting/ar-aging',
                    items: [
                        {
                            title: t('nav.balance_sheet'),
                            href: '/accounting/balance-sheet',
                        },
                        {
                            title: t('nav.ar_aging'),
                            href: '/accounting/ar-aging',
                        },
                        {
                            title: t('nav.ap_aging'),
                            href: '/accounting/ap-aging',
                        },
                        {
                            title: t('nav.audit_trails'),
                            href: '/accounting/audit-trails',
                        },
                    ],
                },
                {
                    title: t('nav.gl_settings_group'),
                    href: '/accounting/tax-setting',
                    items: [
                        {
                            title: t('nav.tax_setting'),
                            href: '/accounting/tax-setting',
                        },
                        {
                            title: t('nav.gl_setting'),
                            href: '/accounting/gl-setting',
                        },
                        {
                            title: t('nav.exchange_rates'),
                            href: '/accounting/exchange-rates',
                        },
                    ],
                },
            ],
        },
        {
            title: t('nav.general_setting'),
            href: '/settings',
            icon: Settings,
            items: [
                {
                    title: t('nav.general_setting'),
                    href: '/settings/general',
                },
                {
                    title: t('nav.payment_terms'),
                    href: '/settings/general/payment-terms',
                },
                {
                    title: t('nav.role_access'),
                    href: '/settings/role-access',
                },
            ],
        },
    ];

    return filterNavByPermission(items);
});

const footerNavItems = computed<NavItem[]>(() => [
    {
        title: t('nav.repository'),
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: t('nav.documentation'),
        href: '/documentation',
        icon: BookOpen,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
