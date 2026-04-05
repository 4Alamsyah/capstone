<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type PurchaseOrderItem = {
    id: number;
    part_number: string | null;
    part_name: string | null;
    quantity: string;
    received_quantity: string;
    unit: string;
    unit_price: string;
    line_total: string;
};

type PurchaseOrderRow = {
    id: number;
    po_number: string;
    status: number;
    order_date: string | null;
    expected_date: string | null;
    currency_code: string;
    subtotal: string;
    supplier: {
        id: number | null;
        name: string | null;
    };
    items: PurchaseOrderItem[];
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

type Props = {
    purchaseOrders: PurchaseOrderRow[];
    filters: {
        search: string;
        status: string;
    };
    pagination: PaginationMeta;
    statusLabels: Record<string, string>;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase', href: '/purchase/po' },
    { title: 'List PO', href: '/purchase/po' },
];

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const statusColors: Record<number, string> = {
    1: 'bg-gray-100 text-gray-700',
    2: 'bg-amber-100 text-amber-700',
    3: 'bg-green-100 text-green-700',
    9: 'bg-red-100 text-red-700',
};

const submitFilter = () => {
    filterForm.get('/purchase/po', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilter = () => {
    filterForm.search = '';
    filterForm.status = '';
    submitFilter();
};

const paginationText = computed(() => {
    if (props.pagination.total === 0) {
        return 'No purchase orders found';
    }

    return `Showing ${props.pagination.from}-${props.pagination.to} of ${props.pagination.total} purchase orders`;
});
</script>

<template>
    <Head title="List PO" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading title="List PO" description="Daftar purchase order ke supplier." />
                <Button as-child>
                    <Link href="/purchase/po/create">+ Register PO</Link>
                </Button>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 p-4">
                <div class="mb-4 flex flex-wrap items-end gap-2">
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Search</span>
                        <Input v-model="filterForm.search" placeholder="PO number, supplier, part..." class="w-64" />
                    </div>
                    <div class="grid gap-1">
                        <span class="text-xs text-muted-foreground">Status</span>
                        <select
                            v-model="filterForm.status"
                            class="h-10 w-40 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All</option>
                            <option v-for="(label, code) in props.statusLabels" :key="code" :value="code">{{ label }}</option>
                        </select>
                    </div>
                    <Button variant="outline" @click="submitFilter">Filter</Button>
                    <Button v-if="filterForm.search || filterForm.status" variant="ghost" @click="clearFilter">Clear</Button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-sidebar-border/70 text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3">PO Number</th>
                                <th class="py-2 pr-3">Supplier</th>
                                <th class="py-2 pr-3">Order Date</th>
                                <th class="py-2 pr-3">Expected Date</th>
                                <th class="py-2 pr-3">Subtotal</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 pr-3">Items</th>
                                <th class="py-2 pr-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.purchaseOrders.length === 0">
                                <td colspan="8" class="py-8 text-center text-muted-foreground">No purchase orders found.</td>
                            </tr>
                            <tr v-for="po in props.purchaseOrders" :key="po.id" class="border-b border-sidebar-border/40 align-top last:border-0">
                                <td class="py-2 pr-3 font-mono font-medium">{{ po.po_number }}</td>
                                <td class="py-2 pr-3">{{ po.supplier.name ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ po.order_date ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ po.expected_date ?? '-' }}</td>
                                <td class="py-2 pr-3 font-semibold">{{ Number(po.subtotal).toLocaleString() }} {{ po.currency_code }}</td>
                                <td class="py-2 pr-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusColors[po.status]">
                                        {{ props.statusLabels[String(po.status)] ?? po.status }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3">
                                    <div class="space-y-1">
                                        <div v-for="item in po.items" :key="item.id" class="text-xs text-muted-foreground">
                                            {{ item.part_number }} - {{ item.part_name }} | {{ item.received_quantity }}/{{ item.quantity }} {{ item.unit }}
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2 pr-3 text-right">
                                    <Button v-if="po.status === 1 || po.status === 2" size="sm" variant="outline" as-child>
                                        <Link :href="`/purchase/po/${po.id}/arrivals/report`">Report Arrival</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between gap-3 text-sm text-muted-foreground">
                    <span>{{ paginationText }}</span>
                    <div class="flex gap-2">
                        <Button v-if="props.pagination.prev_page_url" size="sm" variant="outline" as-child>
                            <Link :href="props.pagination.prev_page_url">Prev</Link>
                        </Button>
                        <Button v-if="props.pagination.next_page_url" size="sm" variant="outline" as-child>
                            <Link :href="props.pagination.next_page_url">Next</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
