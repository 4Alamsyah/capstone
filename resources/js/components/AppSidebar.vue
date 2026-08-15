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

type AuthUser = {
    permissions?: Record<string, boolean>;
};

type SharedPageProps = {
    auth?: {
        user?: AuthUser | null;
    };
};

const page = usePage<SharedPageProps>();

const hasPermission = (key: string): boolean => {
    return Boolean(page.props.auth?.user?.permissions?.[key]);
};

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
            items: undefined,
        },
        {
            title: 'Part',
            href: '/parts',
            icon: PackageSearch,
            items: [
                {
                    title: 'Part List',
                    href: '/parts',
                },
                {
                    title: 'Register Part',
                    href: '/parts/register',
                },
                {
                    title: 'Warehouse',
                    href: '/parts/warehouses',
                },
                {
                    title: 'Stock',
                    href: '/parts/stock',
                },
                {
                    title: 'BOM',
                    href: '/bom',
                },
            ],
        },

        {
            title: 'Manufacturing',
            href: '/work-orders',
            icon: LayoutGrid,
            items: [
                {
                    title: 'MO List',
                    href: '/work-orders',
                },
                {
                    title: 'Report MO',
                    href: '/work-orders/report',
                },
                {
                    title: 'Log MO',
                    href: '/work-orders/logs',
                },
                {
                    title: 'Lead Time',
                    href: '/work-orders/lead-time',
                },
                {
                    title: 'Work Center',
                    href: '/work-centers',
                },
            ],
        },
        {
            title: 'Sales',
            href: '/sales/customer-orders',
            icon: ShoppingCart,
            items: [
                {
                    title: 'Register CO',
                    href: '/sales/customer-orders/create',
                },
                {
                    title: 'Order List',
                    href: '/sales/customer-orders',
                },
                {
                    title: 'Register Quotation',
                    href: '/sales/quotations/create',
                },
                {
                    title: 'Quotation List',
                    href: '/sales/quotations',
                },
                {
                    title: 'Register Invoice',
                    href: '/sales/invoices/create',
                },
                {
                    title: 'Invoice List',
                    href: '/sales/invoices',
                },
                {
                    title: 'Customer Register',
                    href: '/sales/customers',
                },
            ],
        },
        {
            title: 'Purchase',
            href: '/purchase/po',
            icon: Package,
            items: [
                {
                    title: 'Register PO',
                    href: '/purchase/po/create',
                },
                {
                    title: 'List PO',
                    href: '/purchase/po',
                },
                {
                    title: 'Report Arrival',
                    href: '/purchase/po/arrivals',
                },
                {
                    title: 'Log Report',
                    href: '/purchase/po/arrivals/logs',
                },
                {
                    title: 'Register Voucher',
                    href: '/purchase/voucher/create',
                },
                {
                    title: 'List Voucher',
                    href: '/purchase/voucher',
                },
                {
                    title: 'Stock Rekomendasi',
                    href: '/purchase/voucher/stock-recommendations',
                },
                {
                    title: 'Supplier',
                    href: '/suppliers',
                },
            ],
        },
        {
            title: 'Accounting',
            href: '/accounting/general',
            icon: Calculator,
            items: [
                {
                    title: 'General',
                    href: '/accounting/general',
                },
                // {
                //     title: 'Manual Journal',
                //     href: '/accounting/manual-journal',
                // },
                {
                    title: 'Chart of Accounts',
                    href: '/accounting/chart-of-accounts',
                },
                {
                    title: 'Fiscal Periods',
                    href: '/accounting/fiscal-periods',
                },
                {
                    title: 'Journal Entries',
                    href: '/accounting/journal-entries',
                },
                {
                    title: 'Report Jurnal',
                    href: '/accounting/journal-report',
                },
                {
                    title: 'Journal Lines',
                    href: '/accounting/journal-lines',
                },
                {
                    title: 'Audit Trails',
                    href: '/accounting/audit-trails',
                },
                {
                    title: 'Tax Setting',
                    href: '/accounting/tax-setting',
                },
                {
                    title: 'GL Setting',
                    href: '/accounting/gl-setting',
                },
                {
                    title: 'AR Aging',
                    href: '/accounting/ar-aging',
                },
            ],
        },
        {
            title: 'General Setting',
            href: '/settings',
            icon: Settings,
            items: [
                {
                    title: 'General Setting',
                    href: '/settings/general',
                },
                {
                    title: 'Payment Terms',
                    href: '/settings/general/payment-terms',
                },
                {
                    title: 'Role Access',
                    href: '/settings/role-access',
                },
            ],
        },
    ];

    return items.filter((item) => {
        if (item.title === 'Dashboard') return hasPermission('menu.dashboard');
        if (item.title === 'Part') return hasPermission('menu.parts');
        if (item.title === 'Supplier') return hasPermission('menu.suppliers');
        if (item.title === 'Manufacturing')
            return hasPermission('menu.work_orders');
        if (item.title === 'Sales') return hasPermission('menu.sales');
        if (item.title === 'Purchase') return hasPermission('menu.purchase');
        if (item.title === 'Accounting')
            return hasPermission('menu.accounting');
        if (item.title === 'General Setting') {
            const childItems =
                item.items?.filter((child) => {
                    if (child.href === '/settings/general')
                        return hasPermission('menu.settings.general');
                    if (child.href === '/settings/general/payment-terms')
                        return hasPermission('menu.settings.general');
                    if (child.href === '/settings/role-access')
                        return hasPermission('menu.settings.role_access');

                    return true;
                }) ?? [];

            item.items = childItems;

            return childItems.length > 0;
        }

        return true;
    });
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: '/documentation',
        icon: BookOpen,
    },
];
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
