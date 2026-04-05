<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
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

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
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
        title: 'Supplier',
        href: '/suppliers',
        icon: PackageSearch,
    },

    {
        title: 'Work Order',
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
        ],
    },
    {
        title: 'General Setting',
        href: '/settings/general',
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
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
