<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { MoreHorizontal } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type OrderItem = {
    id: number;
    co_number: string;
    status: number;
    order_date: string | null;
    delivery_date: string | null;
    currency_code: string;
    subtotal: string;
    needs_mo_suggestion: boolean;
    customer: {
        id: number | null;
        name: string | null;
    };
    invoice: {
        id: number | null;
        invoice_number: string | null;
    };
    items: {
        id: number;
        part_number: string | null;
        part_name: string | null;
        quantity: string;
        stock_on_hand: number;
        requires_mo: boolean;
    }[];
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type PlanningItem = {
    delivery_date: string;
    orders_count: number;
    needs_mo_count: number;
    delayed_count: number;
};

type Props = {
    orders: OrderItem[];
    filters: {
        search: string;
        status: string;
        delivery_from: string;
        delivery_to: string;
    };
    pagination: PaginationMeta;
    planning: PlanningItem[];
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sales', href: '/sales/customer-orders' },
    { title: 'Order List', href: '/sales/customer-orders' },
];

const searchForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    delivery_from: props.filters.delivery_from ?? '',
    delivery_to: props.filters.delivery_to ?? '',
});

const statusColors: Record<number, string> = {
    1: 'bg-gray-100 text-gray-700',
    2: 'bg-blue-100 text-blue-700',
    3: 'bg-amber-100 text-amber-700',
    4: 'bg-green-100 text-green-700',
    9: 'bg-neutral-200 text-neutral-700',
};

const submitSearch = () => {
    searchForm.get('/sales/customer-orders', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearSearch = () => {
    searchForm.search = '';
    searchForm.status = '';
    searchForm.delivery_from = '';
    searchForm.delivery_to = '';
    submitSearch();
};

const editOrder = (order: OrderItem) => {
    router.visit(`/sales/customer-orders/${order.id}/edit`);
};

const confirmOrder = (order: OrderItem) => {
    if (!window.confirm(`Konfirmasi CO ${order.co_number}?`)) {
        return;
    }

    useForm({}).post(`/sales/customer-orders/${order.id}/confirm`);
};

const moveStatus = (order: OrderItem, nextStatus: 4 | 9) => {
    const targetLabel = props.statusLabels[String(nextStatus)] ?? String(nextStatus);

    if (!window.confirm(`Ubah status ${order.co_number} ke ${targetLabel}?`)) {
        return;
    }

    useForm({ status: nextStatus }).patch(`/sales/customer-orders/${order.id}/status`, {
        onSuccess: () => {
            if (nextStatus === 4) {
                openDeliveryOrderPdf(order);
            }
        },
    });
};

const openDeliveryOrderPdf = (order: OrderItem) => {
    window.open(`/sales/customer-orders/${order.id}/delivery-order`, '_blank', 'noopener');
};

const hasDocumentActions = (order: OrderItem): boolean => {
    return order.status >= 4;
};

const undoReport = (order: OrderItem) => {
    if (order.status === 1) {
        return;
    }

    if (!window.confirm(`Undo report untuk ${order.co_number} dan kembalikan ke Registered?`)) {
        return;
    }

    useForm({}).post(`/sales/customer-orders/${order.id}/undo-report`);
};

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No customer orders found';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} customer orders`;
});
</script>

<template>
    <Head title="Customer Order List" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="Customer Order List" description="Monitor status pesanan, konfirmasi order, dan planning pengiriman CO." />
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/sales/customers">Customer Register</Link>
                    </Button>
                    <Button as-child>
                        <Link href="/sales/customer-orders/create">+ Register CO</Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-end gap-2">
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Search</span>
                        <Input v-model="searchForm.search" placeholder="CO number, customer, part..." class="w-64" />
                    </div>
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Status</span>
                        <select
                            v-model="searchForm.status"
                            class="h-10 w-40 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All</option>
                            <option v-for="(label, statusCode) in statusLabels" :key="statusCode" :value="statusCode">{{ label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Delivery From</span>
                        <Input v-model="searchForm.delivery_from" type="date" class="w-44" />
                    </div>
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Delivery To</span>
                        <Input v-model="searchForm.delivery_to" type="date" class="w-44" />
                    </div>
                    <Button variant="outline" @click="submitSearch">Filter</Button>
                    <Button v-if="searchForm.search || searchForm.status || searchForm.delivery_from || searchForm.delivery_to" variant="ghost" @click="clearSearch">Clear</Button>
                </div>

                <div class="mb-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-md border border-sidebar-border/70 p-3">
                        <div class="text-xs text-muted-foreground">Upcoming Delivery Dates</div>
                        <div class="mt-1 text-2xl font-semibold">{{ planning.length }}</div>
                    </div>
                    <div class="rounded-md border border-sidebar-border/70 p-3">
                        <div class="text-xs text-muted-foreground">Orders Need MO</div>
                        <div class="mt-1 text-2xl font-semibold">
                            {{ planning.reduce((sum, row) => sum + row.needs_mo_count, 0) }}
                        </div>
                    </div>
                    <div class="rounded-md border border-sidebar-border/70 p-3">
                        <div class="text-xs text-muted-foreground">Delayed Orders</div>
                        <div class="mt-1 text-2xl font-semibold text-red-600">
                            {{ planning.reduce((sum, row) => sum + row.delayed_count, 0) }}
                        </div>
                    </div>
                </div>

                <div class="mb-4 overflow-x-auto rounded-md border border-sidebar-border/60">
                    <table class="w-full text-sm">
                        <thead class="bg-sidebar-accent/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="px-3 py-2">Delivery Date</th>
                                <th class="px-3 py-2">Orders</th>
                                <th class="px-3 py-2">Need MO</th>
                                <th class="px-3 py-2">Delayed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in planning" :key="row.delivery_date" class="border-t border-sidebar-border/50">
                                <td class="px-3 py-2">{{ row.delivery_date }}</td>
                                <td class="px-3 py-2">{{ row.orders_count }}</td>
                                <td class="px-3 py-2">{{ row.needs_mo_count }}</td>
                                <td class="px-3 py-2">{{ row.delayed_count }}</td>
                            </tr>
                            <tr v-if="!planning.length" class="border-t border-sidebar-border/50">
                                <td colspan="4" class="px-3 py-4 text-center text-muted-foreground">No planning data for current filter.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">CO Number</th>
                                <th class="py-2 pr-3">Customer</th>
                                <th class="py-2 pr-3">Order Date</th>
                                <th class="py-2 pr-3">Delivery Date</th>
                                <th class="py-2 pr-3">Subtotal</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 pr-3">Availability</th>
                                <th class="py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="orders.length === 0">
                                <td colspan="8" class="py-8 text-center text-muted-foreground">No customer orders found.</td>
                            </tr>
                            <tr v-for="order in orders" :key="order.id" class="border-b border-sidebar-border/40 align-top last:border-0">
                                <td class="py-2 pr-3 font-mono font-medium">{{ order.co_number }}</td>
                                <td class="py-2 pr-3">{{ order.customer.name ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ order.order_date ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ order.delivery_date ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ Number(order.subtotal).toLocaleString() }} {{ order.currency_code }}</td>
                                <td class="py-2 pr-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusColors[order.status]">
                                        {{ statusLabels[String(order.status)] ?? order.status }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="order.needs_mo_suggestion ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'"
                                    >
                                        {{ order.needs_mo_suggestion ? 'Need MO' : 'Ready Stock' }}
                                    </span>
                                </td>
                                <td class="py-2 text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger :as-child="true">
                                            <Button size="icon" variant="ghost" class="h-8 w-8">
                                                <MoreHorizontal class="size-4" />
                                                <span class="sr-only">Open actions menu</span>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-48">
                                            <DropdownMenuItem v-if="order.status === 1" @select.prevent="editOrder(order)">
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator v-if="order.status === 1" />
                                            <DropdownMenuLabel>Status Actions</DropdownMenuLabel>
                                            <DropdownMenuItem v-if="order.status === 1" @select.prevent="confirmOrder(order)">
                                                Confirm
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="order.status === 2 || order.status === 3" @select.prevent="moveStatus(order, 4)">
                                                Delivered
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="order.status === 4" @select.prevent="moveStatus(order, 9)">
                                                Historical
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="order.status !== 1" @select.prevent="undoReport(order)">
                                                Undo Report
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator v-if="hasDocumentActions(order)" />
                                            <DropdownMenuLabel v-if="hasDocumentActions(order)">Documents</DropdownMenuLabel>
                                            <DropdownMenuItem v-if="order.status >= 4" @select.prevent="openDeliveryOrderPdf(order)">
                                                Download DN PDF
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between gap-3 text-sm text-muted-foreground">
                    <span>{{ paginationText }}</span>
                    <div class="flex gap-2">
                        <Button v-if="pagination.prev_page_url" size="sm" variant="outline" as-child>
                            <Link :href="pagination.prev_page_url">Prev</Link>
                        </Button>
                        <Button v-if="pagination.next_page_url" size="sm" variant="outline" as-child>
                            <Link :href="pagination.next_page_url">Next</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
